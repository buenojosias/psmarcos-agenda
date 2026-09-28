<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Mass;
use App\Models\Event;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class UpcomingMasses extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $user = user();
        abort_unless($user->hasRole(UserRoleEnum::PRIEST->value), 403);

        $masses = Mass::query()->visibleTo($user)->upcoming()->whereNull('canceled_at')
            ->with('community:id,name')->orderBy('starts_at')->orderBy('id')->limit(5)->get();
        $masses->whereNull('motivation')->load('schedule:id,motivation');

        return view('livewire.dashboard.upcoming-masses', compact('masses'));
    }
}
