<?php

namespace App\Enums;

enum NotificationTrigger: string
{
    case CitaCreada = 'cita_creada';
    case Recordatorio = 'recordatorio';
    case CitaCancelada = 'cita_cancelada';
    case EventoCreado = 'evento_creado';

    public function label(): string
    {
        return match ($this) {
            self::CitaCreada => 'Confirmación de cita',
            self::Recordatorio => 'Recordatorio',
            self::CitaCancelada => 'Aviso de cancelación',
            self::EventoCreado => 'Evento por horario',
        };
    }

    public function requiresOffset(): bool
    {
        return $this === self::Recordatorio;
    }

    /**
     * The webhook event name that fires this trigger.
     */
    public static function fromWebhookEvent(string $event): array
    {
        return match ($event) {
            'appointment.created' => [self::CitaCreada, self::Recordatorio],
            'appointment.cancelled' => [self::CitaCancelada],
            'event.created' => [self::EventoCreado, self::Recordatorio],
            default => [],
        };
    }
}
