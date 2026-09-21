<?php

declare(strict_types=1);

namespace App\Livewire\Masses;

use App\Models\Mass;
use Livewire\Component;
use App\Models\Community;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\CreateExtraordinaryMassAction;

class CreateExtraordinary extends Component
{
    use Alert;

    public bool $modal = false;

    public int $formKey = 0;

    public mixed $community_id = '';

    public string $motivation = '';

    public string $starts_at = '';

    public string $date = '';

    public string $ends_at = '';

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $pending = null;

    /** @var list<array{date: string, places: list<string>}> */
    #[Locked]
    public array $conflicts = [];

    public function save(CreateExtraordinaryMassAction $action): void
    {
        Gate::authorize('create', Mass::class);
        $this->resetValidation();
        $this->pending   = null;
        $this->conflicts = [];
        $result          = $action->handle($this->only(['community_id', 'motivation', 'date', 'starts_at', 'ends_at']));

        if (! $result['created']) {
            $this->pending   = $result['data'];
            $this->conflicts = $result['conflicts'];

            return;
        }

        $this->complete();
    }

    public function confirm(CreateExtraordinaryMassAction $action): void
    {
        Gate::authorize('create', Mass::class);

        if ($this->pending === null) {
            return;
        }

        $action->handle($this->pending, confirmed: true);
        $this->complete();
    }

    public function cancel(): void
    {
        $this->modal        = false;
        $this->community_id = '';
        $this->motivation   = '';
        $this->date         = '';
        $this->starts_at    = '';
        $this->ends_at      = '';
        $this->pending      = null;
        $this->conflicts    = [];
        $this->formKey++;
        $this->resetValidation();
    }

    public function updatedModal(bool $value): void
    {
        if (! $value) {
            $this->cancel();
        }
    }

    public function render(): View
    {
        return view('livewire.masses.create-extraordinary', [
            'communities' => Community::query()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    private function complete(): void
    {
        $nextFormKey = $this->formKey + 1;
        $this->reset();
        $this->formKey = $nextFormKey;
        $this->dispatch('created');
        $this->success('Cadastro realizado com sucesso.', 'Concluído');
    }
}
