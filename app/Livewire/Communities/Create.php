<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Create extends Component
{
    use Alert;

    public string $name = '';

    public string $alias = '';

    public string $abbreviation = '';

    public ?string $address = null;

    public bool $modal = false;

    public function render(): View
    {
        return view('livewire.communities.create');
    }

    public function save(): void
    {
        Gate::authorize('create', Community::class);

        $this->abbreviation = mb_strtoupper(mb_trim($this->abbreviation));

        $validated = $this->validate([
            'name'         => ['required', 'string', 'max:120'],
            'alias'        => ['required', 'string', 'max:30', Rule::unique('communities', 'alias')],
            'abbreviation' => ['required', 'string', 'max:4', Rule::unique('communities', 'abbreviation')],
            'address'      => ['nullable', 'string', 'max:255'],
        ]);

        Community::create($validated);

        $this->dispatch('created');
        $this->reset();
        $this->success('Comunidade criada com sucesso.', 'Concluído');
    }
}
