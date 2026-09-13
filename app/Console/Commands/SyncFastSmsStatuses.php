<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Exceptions\FastSmsRequestException;
use App\Exceptions\FastSmsUnreachableException;
use App\Models\Origin;
use App\Models\OutboundMessage;
use App\Services\FastSms\FastSmsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFastSmsStatuses extends Command
{
    protected $signature = 'notificaya:sync-fastsms';

    protected $description = 'Consulta a FastSMS el desenlace de los mensajes ya entregados';

    public function handle(): int
    {
        $updated = 0;

        Origin::query()->active()->each(function (Origin $origin) use (&$updated): void {
            if (! $origin->isFullyConfigured()) {
                return;
            }

            $client = FastSmsClient::forOrigin($origin);

            $origin->outboundMessages()
                ->awaitingFastSmsOutcome()
                ->each(function (OutboundMessage $message) use ($client, &$updated): void {
                    if ($this->syncMessage($client, $message)) {
                        $updated++;
                    }
                });
        });

        $this->info("Mensajes actualizados: {$updated}");

        return self::SUCCESS;
    }

    private function syncMessage(FastSmsClient $client, OutboundMessage $message): bool
    {
        try {
            $remote = $client->show((string) $message->fastsms_msg_id);
        } catch (FastSmsUnreachableException|FastSmsRequestException $exception) {
            Log::warning('No se pudo consultar el estado en FastSMS.', [
                'outbound_message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($remote === null) {
            $message->update([
                'status' => NotificationStatus::Error,
                'error_message' => 'FastSMS ya no reconoce este mensaje.',
                'synced_at' => now(),
            ]);

            return true;
        }

        $remoteStatus = (string) ($remote['status'] ?? '');
        $status = NotificationStatus::fromFastSmsStatus($remoteStatus);
        $previous = $message->status;

        $message->update([
            'status' => $status ?? $previous,
            'fastsms_status' => $remoteStatus,
            'error_message' => $remote['error'] ?? null,
            'synced_at' => now(),
        ]);

        return $status !== null && $status !== $previous;
    }
}
