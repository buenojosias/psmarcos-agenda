<?php

namespace App\Enums;

enum EventStatusEnum: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELED = 'canceled';
    case RESCHEDULED = 'rescheduled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::CONFIRMED => 'Confirmado',
            self::CANCELED => 'Cancelado',
            self::RESCHEDULED => 'Alterado - aguardando nova aprovação',
            self::REJECTED => 'Recusado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'green',
            self::CANCELED => 'gray',
            self::RESCHEDULED => 'blue',
            self::REJECTED => 'red',
        };
    }
}
