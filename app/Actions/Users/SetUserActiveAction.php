<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Events\UserAccountChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetUserActiveAction
{
    public function __construct(private ProtectActiveAdministrator $administrators) {}

    public function handle(User $user, User $actor, bool $active): User
    {
        return DB::transaction(function () use ($user, $actor, $active): User {
            $administrators = $this->administrators->lock();
            $actor->refresh();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($actor)->authorize('setActive', $user);
            $this->administrators->ensure($user, $user->getRolesArray(), $active, $administrators);

            if ($user->is_active !== $active) {
                $user->update(['is_active' => $active, 'deactivated_at' => $active ? null : now()]);
                event(new UserAccountChanged($active ? 'user_reactivated' : 'user_deactivated', $user, $actor));
            }

            return $user;
        });
    }
}
