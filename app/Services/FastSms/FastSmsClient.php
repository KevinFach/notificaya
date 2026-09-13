<?php

namespace App\Services\FastSms;

use App\Exceptions\FastSmsRequestException;
use App\Exceptions\FastSmsUnreachableException;
use App\Models\Origin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the FastSMS developer plane (/api/v1), authenticated with a
 * Sanctum personal access token.
 */
class FastSmsClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    public static function forOrigin(Origin $origin): self
    {
        return new self($origin->fastsmsBaseUrl(), (string) $origin->fastsms_token);
    }

    /**
     * Create a single message. Returns the FastSMS msg_id and its initial status.
     *
     * Deliberately performed in a single attempt: FastSMS has no idempotency
     * key, so a retry after an ambiguous failure would send the SMS twice.
     *
     * @param  array<string, mixed>  $payload
     * @return array{msg_id: string, status: string}
     */
    public function createMessage(array $payload): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->post('/api/v1/messages', $payload));

        if ($response->failed()) {
            throw new FastSmsRequestException($this->errorFrom($response), $response->status());
        }

        $msgId = $response->json('msg_id');

        if (! is_string($msgId) || $msgId === '') {
            throw new FastSmsRequestException('FastSMS aceptó el mensaje pero no devolvió un msg_id.', $response->status());
        }

        return [
            'msg_id' => $msgId,
            'status' => (string) ($response->json('status') ?? 'por_enviar'),
        ];
    }

    /**
     * Fetch a message. Returns null when FastSMS no longer knows about it.
     *
     * @return array<string, mixed>|null
     */
    public function show(string $msgId): ?array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get("/api/v1/messages/{$msgId}"), retry: true);

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            throw new FastSmsRequestException($this->errorFrom($response), $response->status());
        }

        return $response->json();
    }

    /**
     * Cancel a message that has not gone out yet.
     *
     * FastSMS answers 422 when the message already reached a final state; that
     * is a legitimate outcome here, not a failure.
     *
     * @return array{cancelled: bool, reason: string|null}
     */
    public function cancel(string $msgId): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->delete("/api/v1/messages/{$msgId}"), retry: true);

        if ($response->successful()) {
            return ['cancelled' => true, 'reason' => null];
        }

        if ($response->status() === 422 || $response->notFound()) {
            return ['cancelled' => false, 'reason' => $this->errorFrom($response)];
        }

        throw new FastSmsRequestException($this->errorFrom($response), $response->status());
    }

    /**
     * Cheap authenticated call used by the panel to validate credentials.
     */
    public function ping(): bool
    {
        return $this->send(
            fn (PendingRequest $request) => $request->get('/api/v1/messages', ['page' => 1]),
            retry: true,
        )->successful();
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     */
    private function send(callable $callback, bool $retry = false): Response
    {
        $request = Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(config('notificaya.http.timeout'))
            ->connectTimeout(config('notificaya.http.connect_timeout'));

        if ($retry) {
            $request = $request->retry(config('notificaya.http.retry_delays'), throw: false);
        }

        try {
            return $callback($request);
        } catch (ConnectionException $exception) {
            throw new FastSmsUnreachableException(
                'No se pudo contactar a FastSMS: '.$exception->getMessage(),
                $exception,
            );
        }
    }

    private function errorFrom(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            foreach (['error', 'message'] as $key) {
                if (is_string($body[$key] ?? null) && $body[$key] !== '') {
                    return $body[$key];
                }
            }
        }

        return "FastSMS respondió {$response->status()}.";
    }
}
