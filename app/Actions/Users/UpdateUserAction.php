<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Validation\Rule;
use App\Events\UserAccountChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateUserAction
{
    public function __construct(private ProtectActiveAdministrator $administrators) {}

    /** @param array{name: string, email: string, password?: ?string, password_confirmation?: ?string, roles: list<string>, communities: list<int>} $data */
    public function handle(User $user, User $actor, array $data): User
    {
        return DB::transaction(function () use ($user, $actor, $data): User {
            $administrators = $this->administrators->lock();
            $actor->refresh();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($actor)->authorize('update', $user);
            Gate::forUser($actor)->authorize('updateRoles', $user);
            $data['email'] = mb_strtolower(mb_trim($data['email']));
            $validated     = Validator::make($data, [
                'name'          => ['required', 'string', 'max:255'],
                'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
                'password'      => ['nullable', 'string', 'min:8', 'confirmed'],
                'roles'         => ['required', 'array', 'min:1'],
                'roles.*'       => ['required', 'string', 'distinct', Rule::in(UserRoleEnum::assignableValues($actor))],
                'communities'   => ['required', 'array', 'min:1'],
                'communities.*' => ['required', 'integer', 'distinct', Rule::exists('communities', 'id')],
            ])->validate();
            $this->administrators->ensure($user, $validated['roles'], $user->is_active, $administrators);
            $oldRoles       = $user->getRolesArray();
            $oldCommunities = $user->communities()->pluck('communities.id')->all();
            $attributes     = ['name' => $validated['name'], 'email' => $validated['email'], 'roles' => $validated['roles']];

            if (! empty($validated['password'])) {
                $attributes['password'] = $validated['password'];
            }

            if ($user->email !== $validated['email']) {
                $attributes['email_verified_at'] = null;
            }
            $user->forceFill($attributes)->save();
            $user->communities()->sync($validated['communities']);

            if ($oldRoles !== $user->getRolesArray()) {
                event(new UserAccountChanged('user_roles_updated', $user, $actor, ['before' => $oldRoles, 'after' => $user->roles]));
            }

            if (array_diff($oldCommunities, $validated['communities']) || array_diff($validated['communities'], $oldCommunities)) {
                event(new UserAccountChanged('user_communities_updated', $user, $actor, ['before' => $oldCommunities, 'after' => $validated['communities']]));
            }

            return $user;
        });
    }
}
