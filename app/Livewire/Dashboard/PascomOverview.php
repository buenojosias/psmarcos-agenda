<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Event;
use Livewire\Component;
use App\Models\EventLog;
use App\Enums\UserRoleEnum;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class PascomOverview extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $user = user();
        abort_unless($user->hasRole(UserRoleEnum::PASCOM->value), 403);

        return view('livewire.dashboard.pascom-overview', [
            'events' => Event::query()->visibleTo($user)->upcoming()->where('starts_at', '>=', now())
                ->where('status', EventStatusEnum::CONFIRMED)->where('advertisable', true)
                ->with(['group:id,name', 'community:id,name'])
                ->orderBy('starts_at')->orderBy('id')->limit(3)->get(),
            'logs' => EventLog::query()->whereIn('event_id', Event::query()->visibleTo($user)->select('id'))
                ->whereIn('action', [EventLogActionEnum::RESCHEDULED, EventLogActionEnum::CANCELED, EventLogActionEnum::APPROVED])
                ->where('created_at', '>=', now()->subDays(14))->with('event:id,name')
                ->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
