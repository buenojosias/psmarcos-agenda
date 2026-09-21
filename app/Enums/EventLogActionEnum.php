<?php

namespace App\Enums;

enum EventLogActionEnum: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELED = 'canceled';
    case RESCHEDULED = 'rescheduled';
    case RESTORED = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Evento criado',
            self::UPDATED => 'Evento atualizado',
            self::SUBMITTED => 'Enviado para aprovação',
            self::APPROVED => 'Evento aprovado',
            self::REJECTED => 'Evento recusado',
            self::CANCELED => 'Evento cancelado',
            self::RESCHEDULED => 'Evento alterado e enviado para nova aprovação',
            self::RESTORED => 'Evento restaurado',
        };
    }
}
