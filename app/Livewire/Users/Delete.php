<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;

class Delete extends Component
{
    public User $user;

    public function render(): string
    {
        return '<div></div>';
    }

    public function confirm(): void
    {
        Gate::authorize('delete', $this->user);
    }

    public function delete(): void
    {
        Gate::authorize('delete', $this->user);
    }
}
