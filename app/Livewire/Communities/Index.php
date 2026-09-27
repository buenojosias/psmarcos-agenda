<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Index extends Component
{
    public array $headers = [
        ['index' => 'name', 'label' => 'Nome', 'sortable' => false],
        ['index' => 'neighborhood', 'label' => '', 'sortable' => false],
    ];

    public function render(): View
    {
        Gate::authorize('viewAny', Community::class);

        return view('livewire.communities.index');
    }

    #[Computed]
    public function rows()
    {
        Gate::authorize('viewAny', Community::class);

        return Community::query()
            ->orderBy('id')
            ->get();
    }
}
