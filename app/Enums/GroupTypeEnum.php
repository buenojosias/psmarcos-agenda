<?php

namespace App\Enums;

enum GroupTypeEnum: string
{
    case PASTORAL = 'pastoral';
    case MOVEMENT = 'movement';
    case GROUP = 'group';
    case COUNCIL = 'council';
    case MINISTRY = 'ministry';
    case SERVICE = 'service';
    case COURSE = 'course';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PASTORAL => 'Pastoral',
            self::MOVEMENT => 'Movimento',
            self::GROUP => 'Grupo',
            self::COUNCIL => 'Conselho',
            self::MINISTRY => 'Ministério',
            self::SERVICE => 'Serviço',
            self::COURSE => 'Curso',
            self::OTHER => 'Outro',
        };
    }
}
