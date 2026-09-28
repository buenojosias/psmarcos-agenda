<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProtectActiveAdministrator
{
    /** @return Collection<int, User> */
    public function lock(): Collection
    {
        return User::query()->where('is_active', true)->whereJsonContains('roles', UserRoleEnum::ADMIN->value)->orderBy('id')->lockForUpdate()->get();
    }

    /** @param Collection<int, User> $administrators
     * @param  list<string>  $roles
     */
    public function ensure(User $user, array $roles, bool $active, Collection $administrators): void
    {
        if ($user->is_active && $user->hasRole(UserRoleEnum::ADMIN->value)
            && (! $active || ! in_array(UserRoleEnum::ADMIN->value, $roles, true))
            && $administrators->count() <= 1) {
            throw ValidationException::withMessages(['roles' => 'O último administrador ativo deve permanecer ativo e com o perfil Administrador.']);
        }
    }
}
