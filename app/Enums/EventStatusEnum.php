<?php

declare(strict_types=1);

namespace App\Enums;

enum EventStatusEnum: string
{
    case PENDING     = 'pending';
    case CONFIRMED   = 'confirmed';
    case CANCELED    = 'canceled';
    case RESCHEDULED = 'rescheduled';
    case REFUSED     = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::PENDING     => 'Pendente',
            self::CONFIRMED   => 'Confirmado',
            self::CANCELED    => 'Cancelado',
            self::RESCHEDULED => 'Alterado - aguardando nova aprovação',
            self::REFUSED     => 'Recusado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING     => 'yellow',
            self::CONFIRMED   => 'green',
            self::CANCELED    => 'gray',
            self::RESCHEDULED => 'blue',
            self::REFUSED     => 'red',
        };
    }
}
