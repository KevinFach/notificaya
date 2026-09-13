<?php

namespace App\Enums;

enum WebhookDeliveryStatus: string
{
    case Recibido = 'recibido';
    case Procesado = 'procesado';
    case Ignorado = 'ignorado';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Recibido => 'Recibido',
            self::Procesado => 'Procesado',
            self::Ignorado => 'Ignorado',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Recibido => 'gray',
            self::Procesado => 'success',
            self::Ignorado => 'warning',
            self::Error => 'danger',
        };
    }
}
