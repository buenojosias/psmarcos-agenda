<?php

namespace App\Enums;

enum EventTypeEnum: string
{
    case MASS = 'mass';
    case MEETING = 'meeting';
    case REHEARSAL = 'rehearsal';
    case PARTY = 'party';
    case FOOD = 'food';
    case COURSE = 'course';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MASS => 'Missa/celebração',
            self::MEETING => 'Reunião',
            self::REHEARSAL => 'Ensaio',
            self::PARTY => 'Festa',
            self::FOOD => 'Almoço, jantar ou café',
            self::COURSE => 'Curso, treinamento ou formação',
            self::OTHER => 'Outro',
        };
    }
}
