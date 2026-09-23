<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\Url;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Show extends Component
{
    public Community $community;

    #[Url(except: 'information')]
    public string $tab = 'information';

    public function mount(Community $community): void
    {
        Gate::authorize('view', $community);

        $this->community = $community;
        $this->normalizeTab();
    }

    public function updatedTab(): void
    {
        $this->normalizeTab();
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
            'groups' => $this->tab === 'groups'
                ? $this->community->groups()->orderBy('name')->get(['id', 'name', 'abbreviation', 'type'])
                : null,
        ]);
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, ['information', 'groups', 'spaces', 'calendar'], true)) {
            $this->tab = 'information';
        }
    }
}
