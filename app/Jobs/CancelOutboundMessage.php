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
 * Pulls a message back from FastSMS.
 *
 * FastSMS only cancels locally: a message already pushed to a provider can
 * still go out. That outcome is recorded rather than hidden.
 */
class CancelOutboundMessage implements ShouldQueue
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

        if ($message === null || $message->status->isFinal()) {
            return;
        }

        if ($message->fastsms_msg_id === null) {
            $message->update([
                'status' => NotificationStatus::Cancelado,
                'error_message' => 'Cancelado antes de entregarse a FastSMS.',
            ]);

            return;
        }

        $origin = $message->origin;

        if ($origin === null || ! $origin->isFullyConfigured()) {
            return;
        }

        try {
            $result = FastSmsClient::forOrigin($origin)->cancel($message->fastsms_msg_id);
        } catch (FastSmsUnreachableException|FastSmsRequestException $exception) {
            $message->update(['error_message' => $exception->getMessage()]);

            throw $exception;
        }

        // When FastSMS refuses the cancellation the message already reached a
        // final state there. The local status is left for the sync command to
        // resolve instead of being overwritten with a cancellation that never
        // happened.
        $message->update([
            'status' => $result['cancelled'] ? NotificationStatus::Cancelado : $message->status,
            'error_message' => $result['cancelled'] ? null : $result['reason'],
            'synced_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->message->fresh()?->update([
            'error_message' => 'No se pudo cancelar en FastSMS: '.($exception?->getMessage() ?? 'falla desconocida'),
        ]);
    }
}
