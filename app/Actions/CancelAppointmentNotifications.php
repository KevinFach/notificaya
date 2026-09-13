<?php

namespace App\Actions;

use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Jobs\CancelOutboundMessage;
use App\Jobs\RelayOutboundMessage;
use App\Models\Appointment;
use App\Models\OutboundMessage;
use App\Services\Notifications\NotificationPlanner;

/**
 * Everything that has to happen when an appointment is cancelled: pull back
 * the SMS that have not gone out, then queue the cancellation notice.
 */
class CancelAppointmentNotifications
{
    public function __construct(private readonly NotificationPlanner $planner) {}

    public function handle(Appointment $appointment): void
    {
        $this->withdrawPendingMessages($appointment);

        $this->planner->plan($appointment, [NotificationTrigger::CitaCancelada])
            ->each(fn (OutboundMessage $message) => RelayOutboundMessage::dispatch($message));
    }

    private function withdrawPendingMessages(Appointment $appointment): void
    {
        $appointment->outboundMessages()
            ->cancellable()
            ->where('trigger', '!=', NotificationTrigger::CitaCancelada->value)
            ->get()
            ->each(function (OutboundMessage $message): void {
                if ($message->fastsms_msg_id === null) {
                    $message->update([
                        'status' => NotificationStatus::Cancelado,
                        'error_message' => 'Cancelado antes de entregarse a FastSMS.',
                    ]);

                    return;
                }

                CancelOutboundMessage::dispatch($message);
            });
    }
}
