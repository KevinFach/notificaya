<?php

namespace App\Enums;

enum ReminderOffsetUnit: string
{
    case Minutos = 'minutos';
    case Horas = 'horas';
    case Dias = 'dias';

    public function label(): string
    {
        return match ($this) {
            self::Minutos => 'Minutos',
            self::Horas => 'Horas',
            self::Dias => 'Días',
        };
    }

    public function toMinutes(int $value): int
    {
        return match ($this) {
            self::Minutos => $value,
            self::Horas => $value * 60,
            self::Dias => $value * 60 * 24,
        };
    }
}
