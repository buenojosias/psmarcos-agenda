<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use TallStackUi\Facades\TallStackUi;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->is_active && $user->hasAnyRole([
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::SECRETARY->value,
            UserRoleEnum::CPP->value,
        ]));

        TallStackUi::customize()
            ->layout()
            ->block('main')
            ->replace('p-10', 'p-6 sm:p-8 lg:p-10');
    }
}
