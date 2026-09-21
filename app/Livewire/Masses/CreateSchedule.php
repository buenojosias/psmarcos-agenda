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
use App\Actions\CreateMassScheduleAction;

class CreateSchedule extends Component
{
    use Alert;

    public bool $modal = false;

    public int $formKey = 0;

    public mixed $community_id = '';

    public string $motivation = '';

    public string $starts_at = '';

    public mixed $weekday = '';

    public mixed $duration_minutes = 60;

    public string $valid_from = '';

    public string $valid_until = '';

    public mixed $dateRange = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $pending = null;

    /** @var list<array{date: string, places: list<string>}> */
    #[Locked]
    public array $conflicts = [];

    public function save(CreateMassScheduleAction $action): void
    {
        Gate::authorize('create', Mass::class);
        $this->resetValidation();
        $this->pending   = null;
        $this->conflicts = [];
        $data            = $this->only(['community_id', 'weekday', 'starts_at', 'duration_minutes', 'motivation', 'valid_from', 'valid_until']);
        $dates           = is_array($this->dateRange) ? array_values($this->dateRange) : [];

        if (count($dates) === 2) {
            [$data['valid_from'], $data['valid_until']] = $dates;
        }

        $result = $action->handle($data);

        if (! $result['created']) {
            $this->pending   = $result['data'];
            $this->conflicts = $result['conflicts'];

            return;
        }

        $this->complete();
    }

    public function confirm(CreateMassScheduleAction $action): void
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
        $this->modal            = false;
        $this->community_id     = '';
        $this->weekday          = '';
        $this->starts_at        = '';
        $this->duration_minutes = 60;
        $this->motivation       = '';
        $this->valid_from       = '';
        $this->valid_until      = '';
        $this->dateRange        = null;
        $this->pending          = null;
        $this->conflicts        = [];
        $this->formKey++;
        $this->resetValidation();
    }

    public function updatedModal(bool $value): void
    {
        if (! $value) {
            $this->cancel();
        }
    }

    public function updatedDateRange(mixed $value): void
    {
        $dates             = is_array($value) ? array_values($value) : [];
        $this->valid_from  = (string) ($dates[0] ?? '');
        $this->valid_until = (string) ($dates[1] ?? '');
    }

    public function render(): View
    {
        return view('livewire.masses.create-schedule', [
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
