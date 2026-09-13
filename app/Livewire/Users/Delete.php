<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Renderless;
use Illuminate\Support\Facades\Gate;

class Delete extends Component
{
    use Alert;

    public User $user;

    public function render(): string
    {
        return <<<'HTML'
        <div>
            <x-button.circle icon="trash" color="red" wire:click="confirm" />
        </div>
        HTML;
    }

    #[Renderless]
    public function confirm(): void
    {
        Gate::authorize('delete', $this->user);

        $this->question()
            ->confirm(method: 'delete')
            ->cancel()
            ->send();
    }

    public function delete(): void
    {
        Gate::authorize('delete', $this->user);

        $this->user->delete();

        $this->dispatch('deleted');

        $this->success();
    }
}
