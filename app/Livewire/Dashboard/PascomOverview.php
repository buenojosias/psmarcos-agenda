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
use Illuminate\Database\Eloquent\Builder;

class PascomOverview extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $user = user();
        abort_unless($user->hasRole(UserRoleEnum::PASCOM->value), 403);

        $visibleEvents = fn (): Builder => Event::query()->visibleTo($user)
            ->when(! $user->hasAnyRole([
                UserRoleEnum::ADMIN->value,
                UserRoleEnum::PRIEST->value,
                UserRoleEnum::SECRETARY->value,
                UserRoleEnum::CPP->value,
            ]), fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('is_public', true)
                ->orWhere(fn (Builder $query): Builder => $query->fromUserGroups($user))));

        return view('livewire.dashboard.pascom-overview', [
            'events' => $visibleEvents()->upcoming()->where('starts_at', '>=', now())
                ->where('status', EventStatusEnum::CONFIRMED)->where('advertisable', true)
                ->with(['group:id,name', 'community:id,name'])
                ->orderBy('starts_at')->orderBy('id')->limit(3)->get(),
            'logs' => EventLog::query()->whereIn('event_id', $visibleEvents()->select('id'))
                ->whereIn('action', [EventLogActionEnum::RESCHEDULED, EventLogActionEnum::CANCELED, EventLogActionEnum::APPROVED])
                ->where('created_at', '>=', now()->subDays(14))->with('event:id,name')
                ->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
