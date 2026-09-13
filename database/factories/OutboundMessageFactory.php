<?php

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Models\Appointment;
use App\Models\OutboundMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboundMessage>
 */
class OutboundMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appointment = Appointment::factory();

        return [
            'appointment_id' => $appointment,
            'origin_id' => fn (array $attributes) => Appointment::find($attributes['appointment_id'])?->origin_id,
            'notification_rule_id' => null,
            'trigger' => NotificationTrigger::CitaCreada,
            'destinatario' => fake()->name(),
            'numero' => '+52'.fake()->numerify('##########'),
            'cuerpo' => 'Tu cita quedó agendada.',
            'scheduled_for' => null,
            'status' => NotificationStatus::Pendiente,
            'fastsms_msg_id' => null,
            'fastsms_status' => null,
            'error_message' => null,
            'attempts' => 0,
            'dispatched_at' => null,
            'synced_at' => null,
        ];
    }

    public function handedToFastSms(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::EntregadoAFastSms,
            'fastsms_msg_id' => (string) Str::uuid(),
            'fastsms_status' => 'por_enviar',
            'dispatched_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger' => NotificationTrigger::Recordatorio,
            'status' => NotificationStatus::Programado,
            'scheduled_for' => now()->addDay(),
            'fastsms_msg_id' => (string) Str::uuid(),
            'fastsms_status' => 'programado',
            'dispatched_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Error,
            'error_message' => 'FastSMS respondió 500.',
            'attempts' => 3,
        ]);
    }
}
