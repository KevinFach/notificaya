<?php

namespace Database\Factories;

use App\Enums\NotificationTrigger;
use App\Enums\ReminderOffsetUnit;
use App\Models\NotificationRule;
use App\Models\Origin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRule>
 */
class NotificationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origin_id' => Origin::factory(),
            'name' => 'Confirmación de cita',
            'trigger' => NotificationTrigger::CitaCreada,
            'offset_value' => null,
            'offset_unit' => null,
            'template' => 'Hola {cliente}, tu cita de {servicio} quedó agendada para el {fecha} a las {hora}. Código: {codigo}.',
            'service_ids' => null,
            'is_active' => true,
        ];
    }

    public function reminder(int $value = 24, ReminderOffsetUnit $unit = ReminderOffsetUnit::Horas): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "Recordatorio {$value} {$unit->value}",
            'trigger' => NotificationTrigger::Recordatorio,
            'offset_value' => $value,
            'offset_unit' => $unit,
            'template' => 'Hola {cliente}, te recordamos tu cita de {servicio} el {fecha} a las {hora}.',
        ]);
    }

    public function cancellation(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Aviso de cancelación',
            'trigger' => NotificationTrigger::CitaCancelada,
            'offset_value' => null,
            'offset_unit' => null,
            'template' => 'Hola {cliente}, tu cita de {servicio} del {fecha} a las {hora} fue cancelada.',
        ]);
    }

    public function event(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Confirmación de evento',
            'trigger' => NotificationTrigger::EventoCreado,
            'offset_value' => null,
            'offset_unit' => null,
            'template' => 'Hola {cliente}, se agendó "{evento}" para el {fecha} a las {hora}.',
        ]);
    }

    /**
     * @param  array<int, int>  $serviceIds
     */
    public function forServices(array $serviceIds): static
    {
        return $this->state(fn (array $attributes) => [
            'service_ids' => $serviceIds,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
