<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use Livewire\WithPagination;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class Events extends Component
{
    use WithPagination;

    public Group $group;

    public function mount(Group $group): void
    {
        $this->group = $group;
    }

    public function render(): View
    {
        $user          = auth()->user();
        $canViewStatus = $user !== null && (
            $user->hasAnyRole([
                UserRoleEnum::CPP->value,
                UserRoleEnum::ADMIN->value,
                UserRoleEnum::SECRETARY->value,
                UserRoleEnum::PRIEST->value,
            ]) || ($user->hasRole(UserRoleEnum::MEMBER->value)
                && $this->group->users()->whereKey($user->id)->exists())
        );

        return view('livewire.groups.events', [
            'canViewStatus' => $canViewStatus,
            'events'        => $this->group->events()
                ->with(['primaryReservation.place.community', 'detail'])
                ->when(! $canViewStatus, fn (Builder $query): Builder => $query->where('status', EventStatusEnum::CONFIRMED->value))
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }
}
