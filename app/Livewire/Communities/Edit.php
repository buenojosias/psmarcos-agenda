<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Edit extends Component
{
    use Alert;

    public string $name = '';

    public string $alias = '';

    public string $abbreviation = '';

    public ?string $address = null;

    public bool $modal = false;

    public Community $community;

    public function render(): View
    {
        return view('livewire.communities.edit');
    }

    public function open(): void
    {
        Gate::authorize('update', $this->community);

        $this->resetValidation();
        $this->community->refresh();
        $this->name         = $this->community->name;
        $this->alias        = $this->community->alias;
        $this->abbreviation = $this->community->abbreviation;
        $this->address      = $this->community->address;
        $this->modal        = true;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->community);

        $this->abbreviation = mb_strtoupper(mb_trim($this->abbreviation));

        $validated = $this->validate([
            'name'         => ['required', 'string', 'max:120'],
            'alias'        => ['required', 'string', 'max:30', Rule::unique('communities', 'alias')->ignore($this->community)],
            'abbreviation' => ['required', 'string', 'max:4', Rule::unique('communities', 'abbreviation')->ignore($this->community)],
            'address'      => ['nullable', 'string', 'max:255'],
        ]);

        $this->community->update($validated);

        $this->modal = false;
        $this->dispatch('community-updated');
        $this->success('Comunidade atualizada com sucesso.', 'Concluído');
    }
}
