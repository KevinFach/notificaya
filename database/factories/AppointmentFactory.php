<?php

namespace Database\Factories;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Origin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(2)->setTime(9, 0);

        return [
            'origin_id' => Origin::factory(),
            'lookup_code' => (string) fake()->unique()->numberBetween(10000, 99999),
            'kind' => AppointmentKind::Cita,
            'status' => AppointmentStatus::Confirmed,
            'customer_name' => fake()->name(),
            'customer_phone' => '55'.fake()->numerify('########'),
            'customer_email' => fake()->safeEmail(),
            'service_external_id' => 11,
            'service_name' => 'Corte de cabello',
            'service_slug' => 'corte-de-cabello',
            'event_name' => null,
            'event_type' => null,
            'responsable_email' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'payload' => [],
            'last_synced_at' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
        ]);
    }

    public function event(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => AppointmentKind::Evento,
            'event_name' => 'Firma de contrato',
            'event_type' => 'Firmas',
            'responsable_email' => fake()->safeEmail(),
            'service_external_id' => 18,
            'service_name' => 'Eventos por horario',
            'service_slug' => 'eventos-por-horario',
        ]);
    }

    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_phone' => null,
        ]);
    }
}
