<?php

namespace App\Jobs;

use App\Actions\CancelAppointmentNotifications;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationTrigger;
use App\Exceptions\ReservaYaRequestException;
use App\Models\Appointment;
use App\Models\OutboundMessage;
use App\Models\WebhookDelivery;
use App\Services\Notifications\NotificationPlanner;
use App\Services\ReservaYa\AppointmentPayloadMapper;
use App\Services\ReservaYa\ReservaYaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns one accepted webhook delivery into an appointment plus the SMS it
 * implies. Runs on the queue so the webhook response stays fast.
 */
class ProcessReservaYaEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly WebhookDelivery $delivery) {}

    public function handle(
        AppointmentPayloadMapper $mapper,
        NotificationPlanner $planner,
        CancelAppointmentNotifications $cancelNotifications,
    ): void {
        $this->delivery->loadMissing('origin');
        $origin = $this->delivery->origin;

        if ($origin === null || ! $origin->is_active) {
            $this->delivery->markIgnored('El origen ya no existe o está inactivo.');

            return;
        }

        $event = $this->delivery->event;
        $lookupCode = (string) data_get($this->delivery->payload, 'data.lookup_code');
        $data = (array) data_get($this->delivery->payload, 'data', []);

        if ($origin->verify_with_api) {
            $verified = $this->verifyWithReservaYa($lookupCode);

            if ($verified === null) {
                $this->delivery->markIgnored("ReservaYa ya no reconoce el código {$lookupCode}.");

                return;
            }

            $data = array_merge($data, $verified);
        }

        $appointment = $this->storeAppointment($mapper, $origin->id, $lookupCode, $data, $event);

        if ($appointment->isCancelled()) {
            $cancelNotifications->handle($appointment);
            $this->delivery->markProcessed();

            return;
        }

        $planner->plan($appointment, NotificationTrigger::fromWebhookEvent($event))
            ->each(fn (OutboundMessage $message) => RelayOutboundMessage::dispatch($message));

        $this->delivery->markProcessed();
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->markFailed($exception?->getMessage() ?? 'Falla desconocida al procesar el evento.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyWithReservaYa(string $lookupCode): ?array
    {
        try {
            return ReservaYaClient::forOrigin($this->delivery->origin)->appointment($lookupCode);
        } catch (ReservaYaRequestException $exception) {
            Log::warning('No se pudo verificar la cita contra ReservaYa.', [
                'delivery_id' => $this->delivery->id,
                'lookup_code' => $lookupCode,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeAppointment(
        AppointmentPayloadMapper $mapper,
        int $originId,
        string $lookupCode,
        array $data,
        string $event,
    ): Appointment {
        $appointment = Appointment::firstOrNew([
            'origin_id' => $originId,
            'lookup_code' => $lookupCode,
        ]);

        $appointment->fill($mapper->toFilledAttributes($data, $event));

        if ($event === 'appointment.cancelled') {
            $appointment->status = AppointmentStatus::Cancelled;
        }

        $appointment->payload = $data;
        $appointment->last_synced_at = now();
        $appointment->save();

        return $appointment->load('origin');
    }
}
