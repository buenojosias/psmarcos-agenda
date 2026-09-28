<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Contracts\View\View;

class Pending extends Component
{
    public function mount(): void
    {
        if (auth()->user()->is_active) {
            $this->redirectRoute('dashboard');
        }
    }

    public function render(): View
    {
        return view('livewire.auth.pending');
    }
}
