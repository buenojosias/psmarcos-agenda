<?php

declare(strict_types=1);

namespace App\Enums;

enum EventLogActionEnum: string
{
    case CREATED     = 'created';
    case UPDATED     = 'updated';
    case SUBMITTED   = 'submitted';
    case APPROVED    = 'approved';
    case REFUSED     = 'refused';
    case CANCELED    = 'canceled';
    case RESCHEDULED = 'rescheduled';
    case RESTORED    = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::CREATED     => 'Evento criado',
            self::UPDATED     => 'Evento atualizado',
            self::SUBMITTED   => 'Enviado para aprovação',
            self::APPROVED    => 'Evento aprovado',
            self::REFUSED     => 'Evento recusado',
            self::CANCELED    => 'Evento cancelado',
            self::RESCHEDULED => 'Evento alterado e enviado para nova aprovação',
            self::RESTORED    => 'Evento restaurado',
        };
    }
}
