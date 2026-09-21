<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Create extends Component
{
    public string $registration_type = 'occasional';

    public function mount(): void
    {
        Gate::authorize('create', Event::class);
    }

    public function render(): View
    {
        Gate::authorize('create', Event::class);

        return view('livewire.events.create');
    }
}
