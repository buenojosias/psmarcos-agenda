<?php

namespace App\Enums\Enums;

enum UserRoleEnum: string
{
    case MEMBER = 'member';
    case SECRETARY = 'secretary';
    case PASCOM = 'pascom';
    case CPP = 'cpp';
    case PRIEST = 'priest';
    case ADMIN = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::MEMBER => 'Membro/coordenador(a) de pastoral',
            self::SECRETARY => 'Secretário(a)',
            self::PASCOM => 'Pasconeiro(a)',
            self::CPP => 'Coordenador(a) do CPP',
            self::PRIEST => 'Pároco/vigário',
            self::ADMIN => 'Administrador',
        };
    }
}
