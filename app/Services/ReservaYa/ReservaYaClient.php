<?php

namespace App\Services\ReservaYa;

use App\Exceptions\ReservaYaRequestException;
use App\Models\Origin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the ReservaYa v1 API. One token per business, so the client is
 * always built from an Origin.
 */
class ReservaYaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    public static function forOrigin(Origin $origin): self
    {
        return new self($origin->reservayaBaseUrl(), (string) $origin->reservaya_token);
    }

    /**
     * Fetch an appointment (or scheduled event) by its lookup code.
     * Returns null when ReservaYa no longer knows the code.
     *
     * @return array<string, mixed>|null
     */
    public function appointment(string $lookupCode): ?array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get("/appointments/{$lookupCode}"));

        if ($response->notFound()) {
            return null;
        }

        $this->throwUnlessSuccessful($response);

        return $response->json('data');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function services(): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get('/services'));

        $this->throwUnlessSuccessful($response);

        return $response->json('data', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reminders(string $lookupCode): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get("/appointments/{$lookupCode}/reminders"));

        $this->throwUnlessSuccessful($response);

        return $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelAppointment(string $lookupCode): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->post("/appointments/{$lookupCode}/cancel"));

        $this->throwUnlessSuccessful($response);

        return $response->json('data', []);
    }

    public function ping(): bool
    {
        return $this->send(fn (PendingRequest $request) => $request->get('/services'))->successful();
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     */
    private function send(callable $callback): Response
    {
        $request = Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(config('notificaya.http.timeout'))
            ->connectTimeout(config('notificaya.http.connect_timeout'))
            ->retry(config('notificaya.http.retry_delays'), throw: false);

        try {
            return $callback($request);
        } catch (ConnectionException $exception) {
            throw new ReservaYaRequestException(
                'No se pudo contactar a ReservaYa: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function throwUnlessSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message');

        throw new ReservaYaRequestException(
            is_string($message) && $message !== '' ? $message : "ReservaYa respondió {$response->status()}.",
            $response->status(),
        );
    }
}
