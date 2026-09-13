<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservaYaEventRequest;
use App\Jobs\ProcessReservaYaEvent;
use App\Models\Origin;
use App\Models\WebhookDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservaYaWebhookController extends Controller
{
    /**
     * Accept an event from ReservaYa, store it, and hand the work to the queue.
     *
     * The response is deliberately fast and unconditional: ReservaYa retries on
     * anything that is not a 2xx, so processing happens out of band.
     */
    public function store(StoreReservaYaEventRequest $request): JsonResponse
    {
        /** @var Origin $origin */
        $origin = $request->origin();
        $payload = $request->validated();
        $eventId = $request->header('X-RY-EVENT-ID');
        $hash = WebhookDelivery::hashPayload($payload);

        if ($duplicate = $this->findDuplicate($origin, $eventId, $hash)) {
            return response()->json([
                'delivery_id' => $duplicate->id,
                'status' => 'duplicado',
            ]);
        }

        try {
            $delivery = WebhookDelivery::create([
                'origin_id' => $origin->id,
                'event' => $request->event(),
                'lookup_code' => $request->lookupCode(),
                'external_event_id' => $eventId,
                'payload_hash' => $hash,
                'payload' => $payload,
                'signature_valid' => true,
                'ip' => $request->ip(),
            ]);
        } catch (QueryException $exception) {
            $duplicate = $this->findDuplicate($origin, $eventId, $hash);

            if ($duplicate === null) {
                throw $exception;
            }

            return response()->json([
                'delivery_id' => $duplicate->id,
                'status' => 'duplicado',
            ]);
        }

        ProcessReservaYaEvent::dispatch($delivery);

        return response()->json([
            'delivery_id' => $delivery->id,
            'status' => 'recibido',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Prefer the event id ReservaYa sends. Without one, fall back to the body
     * hash inside a short window so a retry does not create a second SMS.
     */
    private function findDuplicate(Origin $origin, ?string $eventId, string $hash): ?WebhookDelivery
    {
        return WebhookDelivery::query()
            ->whereBelongsTo($origin)
            ->when(
                filled($eventId),
                fn ($query) => $query->where('external_event_id', $eventId),
                fn ($query) => $query
                    ->where('payload_hash', $hash)
                    ->where('created_at', '>=', now()->subSeconds(config('notificaya.webhook.deduplication_window'))),
            )
            ->latest('id')
            ->first();
    }
}
