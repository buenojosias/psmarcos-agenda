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

    public function render(): View
    {
        $this->group->loadCount([
            'events',
            'events as confirmed_events_count'   => fn ($query) => $query->where('status', EventStatusEnum::CONFIRMED->value),
            'events as rejected_events_count'    => fn ($query) => $query->where('status', EventStatusEnum::REJECTED->value),
            'events as pending_events_count'     => fn ($query) => $query->where('status', EventStatusEnum::PENDING->value),
            'events as rescheduled_events_count' => fn ($query) => $query->where('status', EventStatusEnum::RESCHEDULED->value),
            'events as canceled_events_count'    => fn ($query) => $query->where('status', EventStatusEnum::CANCELED->value),
        ]);

        return view('livewire.groups.show');
    }
}
