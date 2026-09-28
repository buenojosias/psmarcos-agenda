<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

enum UserRoleEnum: string
{
    case MEMBER    = 'member';
    case SECRETARY = 'secretary';
    case PASCOM    = 'pascom';
    case CPP       = 'cpp';
    case PRIEST    = 'priest';
    case ADMIN     = 'admin';

    /** @return list<self> */
    public static function assignableBy(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => match ($role) {
            self::ADMIN => $user->hasRole(self::ADMIN->value),
            self::CPP   => $user->hasAnyRole([self::CPP->value, self::ADMIN->value]),
            default     => true,
        }));
    }

    /** @return list<string> */
    public static function assignableValues(User $user): array
    {
        return array_map(fn (self $role): string => $role->value, self::assignableBy($user));
    }

    /** @return list<array{label: string, value: string}> */
    public static function optionsFor(User $user): array
    {
        return array_map(fn (self $role): array => ['label' => $role->label(), 'value' => $role->value], self::assignableBy($user));
    }

    public function label(): string
    {
        return match ($this) {
            self::MEMBER    => 'Membro/coordenador(a) de pastoral',
            self::SECRETARY => 'Secretário(a)',
            self::PASCOM    => 'Pasconeiro(a)',
            self::CPP       => 'Coordenador(a) do CPP',
            self::PRIEST    => 'Pároco/vigário',
            self::ADMIN     => 'Administrador',
        };
    }
}
