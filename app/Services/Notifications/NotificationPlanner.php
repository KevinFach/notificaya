<?php

namespace App\Services\Notifications;

use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Models\Appointment;
use App\Models\NotificationRule;
use App\Models\OutboundMessage;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Collection;

/**
 * Turns the notification rules of an origin into the concrete SMS that have to
 * be handed to FastSMS for a given appointment.
 */
class NotificationPlanner
{
    public function __construct(private readonly MessageTemplateRenderer $renderer) {}

    /**
     * Create the missing outbound messages for the given triggers.
     *
     * Existing rows are never duplicated, so replaying a webhook delivery is
     * safe. Only the newly created messages are returned.
     *
     * @param  array<int, NotificationTrigger>  $triggers
     * @return Collection<int, OutboundMessage>
     */
    public function plan(Appointment $appointment, array $triggers): Collection
    {
        $appointment->loadMissing('origin');
        $origin = $appointment->origin;

        if ($origin === null || $triggers === []) {
            return collect();
        }

        $numero = PhoneNormalizer::normalize($appointment->customer_phone, $origin->phone_prefix);

        if ($numero === null) {
            return collect();
        }

        $rules = $origin->notificationRules()
            ->active()
            ->whereIn('trigger', array_map(fn (NotificationTrigger $trigger) => $trigger->value, $triggers))
            ->get();

        return $rules
            ->filter(fn (NotificationRule $rule) => $rule->appliesToService($appointment->service_external_id))
            ->map(fn (NotificationRule $rule) => $this->messageFor($appointment, $rule, $numero))
            ->filter()
            ->values();
    }

    private function messageFor(Appointment $appointment, NotificationRule $rule, string $numero): ?OutboundMessage
    {
        $scheduledFor = $this->scheduledFor($appointment, $rule);

        if ($rule->trigger === NotificationTrigger::Recordatorio && $scheduledFor === null) {
            return null;
        }

        $cuerpo = $this->renderer->render($rule->template, $appointment);

        if (blank($cuerpo)) {
            return null;
        }

        $message = OutboundMessage::firstOrNew([
            'appointment_id' => $appointment->id,
            'notification_rule_id' => $rule->id,
        ]);

        if ($message->exists) {
            return null;
        }

        $message->fill([
            'origin_id' => $appointment->origin_id,
            'trigger' => $rule->trigger,
            'destinatario' => $appointment->customer_name,
            'numero' => $numero,
            'cuerpo' => $cuerpo,
            'scheduled_for' => $scheduledFor,
            'status' => NotificationStatus::Pendiente,
        ])->save();

        return $message;
    }

    /**
     * Reminders are anchored to the appointment start; everything else goes out
     * immediately. A reminder whose moment already passed is dropped.
     */
    private function scheduledFor(Appointment $appointment, NotificationRule $rule): ?\Illuminate\Support\Carbon
    {
        if ($rule->trigger !== NotificationTrigger::Recordatorio || $appointment->starts_at === null) {
            return null;
        }

        $moment = $appointment->starts_at->copy()->subMinutes($rule->offsetInMinutes());

        return $moment->isFuture() ? $moment : null;
    }
}
