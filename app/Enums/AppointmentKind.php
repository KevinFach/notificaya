<?php

namespace App\Enums;

enum AppointmentKind: string
{
    case Cita = 'cita';
    case Evento = 'evento';

    public function label(): string
    {
        return match ($this) {
            self::Cita => 'Cita',
            self::Evento => 'Evento por horario',
        };
    }
}
