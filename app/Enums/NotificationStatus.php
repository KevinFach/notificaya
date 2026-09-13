<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pendiente = 'pendiente';
    case EntregadoAFastSms = 'entregado_a_fastsms';
    case Programado = 'programado';
    case Enviado = 'enviado';
    case Cancelado = 'cancelado';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EntregadoAFastSms => 'Entregado a FastSMS',
            self::Programado => 'Programado',
            self::Enviado => 'Enviado',
            self::Cancelado => 'Cancelado',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::EntregadoAFastSms, self::Programado => 'info',
            self::Enviado => 'success',
            self::Cancelado => 'warning',
            self::Error => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Enviado, self::Cancelado], true);
    }

    /**
     * Map a FastSMS message status onto the local status.
     */
    public static function fromFastSmsStatus(string $status): ?self
    {
        return match ($status) {
            'enviado' => self::Enviado,
            'cancelado' => self::Cancelado,
            'error' => self::Error,
            'programado' => self::Programado,
            'por_enviar', 'en_cola' => self::EntregadoAFastSms,
            default => null,
        };
    }
}
