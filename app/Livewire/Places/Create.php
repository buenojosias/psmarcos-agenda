<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use App\Models\Place;
use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\On;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Create extends Component
{
    use Alert;

    public Community $community;

    #[Locked]
    public ?int $mainPlaceId = null;

    public string $name = '';

    public bool $modal = false;

    #[On('create-place')]
    public function open(?int $mainPlaceId = null): void
    {
        Gate::authorize('create', Place::class);
        Gate::authorize('view', $this->community);

        if ($mainPlaceId !== null) {
            $this->community->places()->findOrFail($mainPlaceId);
        }

        $this->reset('name', 'mainPlaceId');
        $this->resetValidation();
        $this->mainPlaceId = $mainPlaceId;
        $this->modal       = true;
    }

    public function save(): void
    {
        Gate::authorize('create', Place::class);
        Gate::authorize('view', $this->community);

        if ($this->mainPlaceId !== null) {
            $this->community->places()->findOrFail($this->mainPlaceId);
        }

        $this->name = mb_trim($this->name);
        $validated  = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->community->places()->create([
            ...$validated,
            'main_place_id' => $this->mainPlaceId,
        ]);

        $this->reset('name', 'mainPlaceId', 'modal');
        $this->dispatch('created');
        $this->success('Espaço criado com sucesso.', 'Concluído');
    }

    public function render(): View
    {
        return view('livewire.places.create');
    }
}
