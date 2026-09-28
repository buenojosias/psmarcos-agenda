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

class ApproveUserAction
{
    public function __construct(private ProtectActiveAdministrator $administrators) {}

    /** @param list<string> $roles */
    public function handle(User $user, User $actor, array $roles): User
    {
        return DB::transaction(function () use ($user, $actor, $roles): User {
            $this->administrators->lock();
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $user  = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($actor)->authorize('approve', $user);
            Validator::make(['roles' => $roles], [
                'roles'   => ['required', 'array', 'min:1'],
                'roles.*' => ['required', 'string', 'distinct', Rule::in(UserRoleEnum::assignableValues($actor))],
            ])->validate();

            $user->update(['roles' => $roles, 'is_active' => true, 'approved_by_user_id' => $actor->id, 'approved_at' => now(), 'deactivated_at' => null]);
            event(new UserAccountChanged('user_approved', $user, $actor));

            return $user;
        });
    }
}
