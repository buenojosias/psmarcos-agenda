<?php

declare(strict_types=1);

namespace App\Livewire\Events\Forms;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use App\Actions\CheckPlaceAvailabilityAction;
use App\Actions\GenerateRecurringDatesAction;
use Illuminate\Validation\ValidationException;

class Recurring extends Multiple
{
    public mixed $weekday = '';

    public string $periodicity = '';

    public string $monthly_position = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public function validateDraft(CheckPlaceAvailabilityAction $checkPlaceAvailability): void
    {
        $this->dates          = [];
        $this->occurrences    = [];
        $this->draftValidated = false;
        $this->conflictsSlide = false;

        $criteria = $this->validate($this->recurrenceRules());

        if (filled($criteria['start_date'] ?? null) && filled($criteria['end_date'] ?? null) && $criteria['end_date'] < $criteria['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => 'A data de encerramento deve ser igual ou posterior à data de início.',
            ]);
        }

        $this->dates = (new GenerateRecurringDatesAction)->handle(
            (int) $criteria['weekday'],
            $criteria['periodicity'],
            $criteria['monthly_position'] ?? null,
            ($criteria['start_date'] ?? null) ?: null,
            ($criteria['end_date'] ?? null) ?: null,
            $this->starts_time,
            CarbonImmutable::now(),
        );

        if ($this->dates === []) {
            throw ValidationException::withMessages([
                'end_date' => 'Não há ocorrências futuras para o período informado.',
            ]);
        }

        parent::validateDraft($checkPlaceAvailability);
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            ...parent::rules(),
            ...$this->recurrenceRules(),
            'dates' => ['required', 'array', 'min:1'],
        ];
    }

    protected function formView(): string
    {
        return 'livewire.events.forms.recurring';
    }

    /** @return array<string, array<int, mixed>> */
    private function recurrenceRules(): array
    {
        return [
            'weekday'          => ['required', 'integer', 'between:0,6'],
            'periodicity'      => ['required', Rule::in(['weekly', 'biweekly', 'monthly'])],
            'monthly_position' => [Rule::requiredIf($this->periodicity === 'monthly'), 'nullable', Rule::in(['1', '2', '3', '4', 'last'])],
            'start_date'       => ['nullable', 'date_format:Y-m-d'],
            'end_date'         => ['nullable', 'date_format:Y-m-d'],
            'starts_time'      => ['required', 'date_format:H:i'],
        ];
    }
}
