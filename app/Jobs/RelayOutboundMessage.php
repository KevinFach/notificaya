<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\FastSmsRequestException;
use App\Exceptions\FastSmsUnreachableException;
use App\Models\OutboundMessage;
use App\Services\FastSms\FastSmsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Hands one message to FastSMS. Immediate sends go out with no schedule;
 * reminders carry fecha_envio/hora_envio so FastSMS holds them until due.
 */
class RelayOutboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly OutboundMessage $message) {}

    public function handle(): void
    {
        $message = $this->message->fresh(['origin']);

        if ($message === null || ! $this->isRelayable($message)) {
            return;
        }

        $origin = $message->origin;

        if ($origin === null || ! $origin->isFullyConfigured()) {
            $message->update([
                'status' => NotificationStatus::Error,
                'error_message' => 'El origen no tiene credenciales de FastSMS configuradas.',
            ]);

            return;
        }

        $message->increment('attempts');

        try {
            $result = FastSmsClient::forOrigin($origin)->createMessage($this->payloadFor($message));
        } catch (FastSmsUnreachableException $exception) {
            // The request may or may not have created the message. Retrying
            // could send the same SMS twice, so this stops here for a human.
            $message->update([
                'status' => NotificationStatus::Error,
                'error_message' => $exception->getMessage().' Revísalo antes de reenviar: el SMS pudo haberse creado.',
            ]);

            $this->fail($exception);

            return;
        } catch (FastSmsRequestException $exception) {
            $message->update([
                'status' => NotificationStatus::Error,
                'error_message' => $exception->getMessage(),
            ]);

            if ($exception->status >= 500 || $exception->status === 0) {
                throw $exception;
            }

            return;
        }

        $message->update([
            'status' => NotificationStatus::fromFastSmsStatus($result['status']) ?? NotificationStatus::EntregadoAFastSms,
            'fastsms_msg_id' => $result['msg_id'],
            'fastsms_status' => $result['status'],
            'error_message' => null,
            'dispatched_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $this->message->fresh();

        if ($message === null || $message->status->isFinal()) {
            return;
        }

        // handle() already explains the failures it recognises; this only fills
        // in for the ones it never saw.
        $message->update([
            'status' => NotificationStatus::Error,
            'error_message' => $message->error_message
                ?: ($exception?->getMessage() ?? 'Falla desconocida al entregar el mensaje a FastSMS.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(OutboundMessage $message): array
    {
        $payload = array_filter([
            'nombre' => $message->destinatario,
            'numero' => $message->numero,
            'mensaje' => $message->cuerpo,
        ], fn (mixed $value) => filled($value));

        if ($message->scheduled_for !== null) {
            $scheduled = $message->scheduled_for->copy()->setTimezone($message->origin->fastsmsTimezone());

            $payload['fecha_envio'] = $scheduled->format('Y-m-d');
            $payload['hora_envio'] = $scheduled->format('H:i');
        }

        return $payload;
    }

    private function isRelayable(OutboundMessage $message): bool
    {
        return in_array($message->status, [
            NotificationStatus::Pendiente,
            NotificationStatus::Error,
        ], true) && $message->fastsms_msg_id === null;
    }
}
