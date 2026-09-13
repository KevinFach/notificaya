<?php

namespace Database\Seeders;

use App\Enums\NotificationTrigger;
use App\Enums\ReminderOffsetUnit;
use App\Models\Origin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A ready-to-configure origin with the four rules a typical business wants.
 * The tokens are placeholders: fill them in from the panel.
 */
class DemoOriginSeeder extends Seeder
{
    public function run(): void
    {
        $origin = Origin::query()->firstOrCreate(
            ['slug' => 'negocio-demo'],
            [
                'name' => 'Negocio demo',
                'is_active' => true,
                'reservaya_base_url' => config('notificaya.reservaya.base_url'),
                'reservaya_token' => null,
                'verify_with_api' => false,
                'webhook_secret' => Str::random(64),
                'fastsms_base_url' => config('notificaya.fastsms.base_url'),
                'fastsms_token' => null,
                'timezone' => config('notificaya.defaults.timezone'),
                'phone_prefix' => config('notificaya.defaults.phone_prefix'),
            ],
        );

        $rules = [
            [
                'name' => 'Confirmación de cita',
                'trigger' => NotificationTrigger::CitaCreada,
                'offset_value' => null,
                'offset_unit' => null,
                'template' => 'Hola {cliente}, tu cita de {servicio} en {negocio} quedó agendada para el {fecha} a las {hora}. Código: {codigo}.',
            ],
            [
                'name' => 'Recordatorio 24 horas antes',
                'trigger' => NotificationTrigger::Recordatorio,
                'offset_value' => 24,
                'offset_unit' => ReminderOffsetUnit::Horas,
                'template' => 'Hola {cliente}, te recordamos tu cita de {servicio} mañana {fecha} a las {hora} en {negocio}.',
            ],
            [
                'name' => 'Recordatorio 2 horas antes',
                'trigger' => NotificationTrigger::Recordatorio,
                'offset_value' => 2,
                'offset_unit' => ReminderOffsetUnit::Horas,
                'template' => 'Hola {cliente}, tu cita de {servicio} en {negocio} es hoy a las {hora}. Te esperamos.',
            ],
            [
                'name' => 'Aviso de cancelación',
                'trigger' => NotificationTrigger::CitaCancelada,
                'offset_value' => null,
                'offset_unit' => null,
                'template' => 'Hola {cliente}, tu cita de {servicio} del {fecha} a las {hora} fue cancelada. Código: {codigo}.',
            ],
            [
                'name' => 'Confirmación de evento por horario',
                'trigger' => NotificationTrigger::EventoCreado,
                'offset_value' => null,
                'offset_unit' => null,
                'template' => 'Hola {cliente}, se agendó "{evento}" para el {fecha} a las {hora} en {negocio}.',
            ],
        ];

        foreach ($rules as $rule) {
            $origin->notificationRules()->firstOrCreate(['name' => $rule['name']], $rule);
        }

        $this->command?->info("Origen demo listo. Secreto del webhook: {$origin->webhook_secret}");
    }
}
