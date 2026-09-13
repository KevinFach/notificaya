<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use Illuminate\Support\Str;

/**
 * Replaces the placeholders of a notification rule template with the data of
 * an appointment. Dates are rendered in the timezone of the origin so the SMS
 * reads the same hour the customer saw when booking.
 */
class MessageTemplateRenderer
{
    /**
     * @var array<int, string>
     */
    public const PLACEHOLDERS = [
        '{cliente}',
        '{negocio}',
        '{servicio}',
        '{fecha}',
        '{hora}',
        '{codigo}',
        '{evento}',
        '{responsable}',
    ];

    public function render(string $template, Appointment $appointment): string
    {
        return Str::squish(strtr($template, $this->replacements($appointment)));
    }

    /**
     * @return array<string, string>
     */
    public function replacements(Appointment $appointment): array
    {
        $timezone = $appointment->origin?->timezone ?? config('notificaya.defaults.timezone');
        $startsAt = $appointment->starts_at?->copy()->setTimezone($timezone);

        return [
            '{cliente}' => (string) ($appointment->customer_name ?? ''),
            '{negocio}' => (string) ($appointment->origin?->name ?? ''),
            '{servicio}' => (string) ($appointment->service_name ?? ''),
            '{fecha}' => $startsAt?->locale('es')->isoFormat('dddd D [de] MMMM') ?? '',
            '{hora}' => $startsAt?->format('H:i') ?? '',
            '{codigo}' => (string) $appointment->lookup_code,
            '{evento}' => (string) ($appointment->event_name ?? ''),
            '{responsable}' => (string) ($appointment->responsable_email ?? ''),
        ];
    }
}
