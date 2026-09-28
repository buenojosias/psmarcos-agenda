<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Validation\Rule;
use App\Events\UserAccountChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = mb_strtolower(mb_trim($input['email']));
        }

        Validator::make($input, [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'communities'   => ['required', 'array', 'min:1'],
            'communities.*' => ['required', 'integer', 'distinct', Rule::exists('communities', 'id')],
            'password'      => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name'      => $input['name'],
                'email'     => mb_strtolower(mb_trim($input['email'])),
                'password'  => Hash::make($input['password']),
                'is_active' => false,
                'roles'     => [UserRoleEnum::MEMBER->value],
            ]);

            $user->communities()->sync($input['communities']);
            event(new UserAccountChanged('user_registered', $user));

            return $user;
        });
    }
}
