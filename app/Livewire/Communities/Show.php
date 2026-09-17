<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Show extends Component
{
    public Community $community;

    public function mount(Community $community): void
    {
        Gate::authorize('view', $community);

        $this->community = $community;
    }

    public function refreshCommunity(): void
    {
        Gate::authorize('view', $this->community);

        $this->community->refresh();
    }

    public function render(): View
    {
        Gate::authorize('view', $this->community);

        return view('livewire.communities.show', [
            'groups' => $this->community->groups()->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }
}
