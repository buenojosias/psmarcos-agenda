<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;

class Show extends Component
{
    public Group $group;

    public function mount(Group $group): void
    {
        $this->group = $group->load('community');
    }

    public function refreshGroup(): void
    {
        $this->group->refresh()->load('community');
    }

    public function render(): View
    {
        $user               = auth()->user();
        $showAllEventCounts = $user !== null && (! $user->isMemberOnly()
            || $this->group->users()->whereKey($user->id)->exists());

        $counts = [
            'events as confirmed_events_count' => fn ($query) => $query->where('status', EventStatusEnum::CONFIRMED->value),
        ];

        if ($showAllEventCounts) {
            $counts = array_merge(['events'], $counts, [
                'events as refused_events_count'     => fn ($query) => $query->where('status', EventStatusEnum::REFUSED->value),
                'events as pending_events_count'     => fn ($query) => $query->where('status', EventStatusEnum::PENDING->value),
                'events as rescheduled_events_count' => fn ($query) => $query->where('status', EventStatusEnum::RESCHEDULED->value),
                'events as canceled_events_count'    => fn ($query) => $query->where('status', EventStatusEnum::CANCELED->value),
            ]);
        }

        $this->group->loadCount($counts);

        return view('livewire.groups.show', ['showAllEventCounts' => $showAllEventCounts]);
    }
}
