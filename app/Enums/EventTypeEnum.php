<?php

declare(strict_types=1);

namespace App\Enums;

enum EventTypeEnum: string
{
    case MEETING    = 'meeting';
    case REHEARSAL  = 'rehearsal';
    case PARTY      = 'party';
    case FELLOWSHIP = 'fellowship';
    case FOOD       = 'food';
    case COURSE     = 'course';
    case OTHER      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MEETING    => 'Reunião',
            self::REHEARSAL  => 'Ensaio',
            self::PARTY      => 'Festa',
            self::FELLOWSHIP => 'Confraternização',
            self::FOOD       => 'Almoço, jantar ou café',
            self::COURSE     => 'Curso, treinamento ou formação',
            self::OTHER      => 'Outro',
        };
    }
}
