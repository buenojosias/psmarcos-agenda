<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\Lazy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Lazy]
class Places extends Component
{
    public Community $community;

    public function placeholder(): string
    {
        return '<div role="status" class="text-sm text-gray-500 dark:text-dark-400">Carregando espaços...</div>';
    }

    public function render(): View
    {
        Gate::authorize('view', $this->community);

        return view('livewire.communities.places', [
            'places' => $this->community->places()
                ->whereNull('main_place_id')
                ->with(['subplaces' => fn (HasMany $query): HasMany => $query
                    ->where('community_id', $this->community->id)
                    ->orderBy('name')
                    ->select(['id', 'main_place_id', 'name'])])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
