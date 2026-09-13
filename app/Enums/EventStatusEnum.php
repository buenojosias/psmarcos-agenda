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
            self::RESCHEDULED => 'Remarcado',
            self::REJECTED => 'Rejeitado',
        };
    }
    
}
