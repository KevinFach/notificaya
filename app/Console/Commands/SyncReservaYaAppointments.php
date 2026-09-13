<?php

namespace App\Console\Commands;

use App\Actions\CancelAppointmentNotifications;
use App\Enums\AppointmentStatus;
use App\Exceptions\ReservaYaRequestException;
use App\Models\Appointment;
use App\Models\Origin;
use App\Services\ReservaYa\ReservaYaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for lost webhooks.
 *
 * ReservaYa cannot list appointments, so this only re-checks the ones we
 * already know about and that still have SMS armed. It exists to catch
 * cancellations whose webhook never arrived.
 */
class SyncReservaYaAppointments extends Command
{
    protected $signature = 'notificaya:sync-reservaya';

    protected $description = 'Revisa en ReservaYa las citas con SMS pendientes para detectar cancelaciones perdidas';

    public function handle(CancelAppointmentNotifications $cancelNotifications): int
    {
        $cancelled = 0;

        Origin::query()->active()->each(function (Origin $origin) use ($cancelNotifications, &$cancelled): void {
            if (blank($origin->reservaya_token)) {
                return;
            }

            $client = ReservaYaClient::forOrigin($origin);

            $origin->appointments()
                ->where('status', AppointmentStatus::Confirmed->value)
                ->where('starts_at', '>', now())
                ->withPendingMessages()
                ->oldest('last_synced_at')
                ->limit(config('notificaya.resync.max_appointments_per_run'))
                ->get()
                ->each(function (Appointment $appointment) use ($client, $cancelNotifications, &$cancelled): void {
                    if ($this->refresh($client, $cancelNotifications, $appointment)) {
                        $cancelled++;
                    }
                });
        });

        $this->info("Citas canceladas detectadas: {$cancelled}");

        return self::SUCCESS;
    }

    private function refresh(
        ReservaYaClient $client,
        CancelAppointmentNotifications $cancelNotifications,
        Appointment $appointment,
    ): bool {
        try {
            $remote = $client->appointment($appointment->lookup_code);
        } catch (ReservaYaRequestException $exception) {
            Log::warning('No se pudo resincronizar la cita con ReservaYa.', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $appointment->last_synced_at = now();

        // A code ReservaYa no longer recognises is treated as cancelled: there
        // is no appointment left to remind anyone about.
        if ($remote !== null && ($remote['status'] ?? null) !== AppointmentStatus::Cancelled->value) {
            $appointment->save();

            return false;
        }

        $appointment->status = AppointmentStatus::Cancelled;
        $appointment->save();

        $cancelNotifications->handle($appointment->load('origin'));

        return true;
    }
}
