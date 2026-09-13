<?php

namespace App\Http\Middleware;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Origin;
use App\Models\WebhookDelivery;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an inbound ReservaYa webhook.
 *
 * The business is named in `X-RY-ORIGIN` and the body is signed with that
 * origin's shared secret in `X-RY-SIGNATURE`. The resolved Origin is left in
 * the request attributes for the controller.
 */
class VerifyReservaYaSignature
{
    public const ORIGIN_ATTRIBUTE = 'reservaya_origin';

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-RY-ORIGIN');

        if (blank($slug)) {
            return $this->unauthorized('Falta el encabezado X-RY-ORIGIN.');
        }

        $origin = Origin::query()->active()->where('slug', $slug)->first();

        if ($origin === null) {
            return $this->unauthorized('Origen no autorizado o inactivo.');
        }

        $signature = (string) $request->header('X-RY-SIGNATURE');

        if (blank($signature)) {
            return $this->unauthorized('Falta la firma X-RY-SIGNATURE.');
        }

        if (! $this->signatureMatches($request, $origin, $signature)) {
            $this->recordRejection($request, $origin);

            return $this->unauthorized('Firma inválida.');
        }

        $request->attributes->set(self::ORIGIN_ATTRIBUTE, $origin);

        return $next($request);
    }

    private function signatureMatches(Request $request, Origin $origin, string $signature): bool
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) $origin->webhook_secret);

        return hash_equals('sha256='.$expected, $signature) || hash_equals($expected, $signature);
    }

    /**
     * A wrong signature is almost always a rotated secret. Leaving a trace in
     * the panel is what makes that diagnosable.
     */
    private function recordRejection(Request $request, Origin $origin): void
    {
        $payload = $request->json()->all();

        WebhookDelivery::create([
            'origin_id' => $origin->id,
            'event' => (string) ($payload['event'] ?? 'desconocido'),
            'lookup_code' => data_get($payload, 'data.lookup_code'),
            'external_event_id' => null,
            'payload_hash' => WebhookDelivery::hashPayload($payload),
            'payload' => $payload,
            'signature_valid' => false,
            'ip' => $request->ip(),
            'status' => WebhookDeliveryStatus::Ignorado,
            'error_message' => 'Firma inválida.',
            'processed_at' => now(),
        ]);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['error' => $message], Response::HTTP_UNAUTHORIZED);
    }
}
