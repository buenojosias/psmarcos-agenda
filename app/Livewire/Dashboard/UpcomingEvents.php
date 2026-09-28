<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Event;
use Livewire\Component;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class UpcomingEvents extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $user  = user();
        $query = Event::query()->visibleTo($user)->upcoming()->where('status', EventStatusEnum::CONFIRMED)
            ->with(['group:id,name', 'community:id,name']);

        if ($user->isMemberOnly()) {
            $query->withExists(['group as belongs_to_user_group' => fn (Builder $groups): Builder => $groups
                ->whereHas('users', fn (Builder $users): Builder => $users->whereKey($user->id))])
                ->orderByDesc('belongs_to_user_group');
        }

        $events = $query->orderBy('starts_at')->orderBy('id')->limit(5)->get();
        $events->where('is_external', true)->load('detail:id,event_id,external_location_name');
        $events->where('is_external', false)->load([
            'primaryReservation:id,event_id,place_id',
            'primaryReservation.place:id,name,main_place_id',
            'primaryReservation.place.main:id,name',
        ]);

        return view('livewire.dashboard.upcoming-events', [
            'events'     => $events,
            'memberOnly' => $user->isMemberOnly(),
        ]);
    }
}
