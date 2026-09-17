<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\On;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Edit extends Component
{
    use Alert;

    public Community $community;

    #[Locked]
    public ?int $placeId = null;

    public string $name = '';

    public bool $modal = false;

    #[On('edit-place')]
    public function open(int $placeId): void
    {
        Gate::authorize('view', $this->community);

        $place = $this->community->places()->findOrFail($placeId);
        Gate::authorize('update', $place);

        $this->resetValidation();
        $this->placeId = $place->id;
        $this->name    = $place->name;
        $this->modal   = true;
    }

    public function save(): void
    {
        Gate::authorize('view', $this->community);

        $place = $this->community->places()->findOrFail($this->placeId);
        Gate::authorize('update', $place);

        $this->name = mb_trim($this->name);
        $validated  = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $place->update($validated);

        $this->reset('name', 'placeId', 'modal');
        $this->dispatch('place-updated');
        $this->success('Espaço atualizado com sucesso.', 'Concluído');
    }

    public function render(): View
    {
        return view('livewire.places.edit');
    }
}
