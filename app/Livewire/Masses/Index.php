<?php

declare(strict_types=1);

namespace App\Livewire\Masses;

use App\Models\Mass;
use App\Models\Event;
use Livewire\Component;
use App\Models\Community;
use App\Models\MassSchedule;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public mixed $weekday = '';

    #[Url(except: 0)]
    public mixed $community_id = 0;

    #[Url(except: '')]
    public mixed $motivation = '';

    public bool $showPast = false;

    public mixed $dateRange = null;

    public function updated(string $property): void
    {
        if (explode('.', $property)[0] === 'dateRange') {
            $dates = is_array($this->dateRange) ? array_values($this->dateRange) : [];

            if (Validator::make(['dates' => $dates], [
                'dates'   => ['array', 'max:2'],
                'dates.*' => ['nullable', 'date_format:Y-m-d'],
            ])->fails() || empty($dates[0])) {
                $this->dateRange = null;
            } else {
                $this->dateRange = [$dates[0], $dates[1] ?? null];
            }
        }

        if (in_array(explode('.', $property)[0], ['weekday', 'community_id', 'motivation', 'showPast', 'dateRange'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);

        $weekday          = is_int($this->weekday) ? (string) $this->weekday : $this->weekday;
        $this->weekday    = in_array($weekday, ['0', '1', '2', '3', '4', '5', '6'], true) ? $weekday : '';
        $this->motivation = is_string($this->motivation) ? mb_substr($this->motivation, 0, 255) : '';
        $communityId      = is_int($this->community_id) || is_string($this->community_id)
            ? filter_var($this->community_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : false;
        $this->community_id = $communityId === false ? 0 : $communityId;

        $query = Mass::query()
            ->withReservationConflict()
            ->visibleTo(auth()->user());

        if (! $this->showPast) {
            $query->where('starts_at', '>=', today());
        }

        $dates = is_array($this->dateRange) ? array_values($this->dateRange) : [];

        if (count($dates) === 2 && $dates[0] !== null && $dates[1] !== null) {
            sort($dates);
            $query->whereDate('starts_at', '>=', $dates[0])->whereDate('starts_at', '<=', $dates[1]);
        }
        $communities = Community::query()->select('id', 'name')->orderBy('name')->get();

        if ($this->weekday !== '') {
            $weekdayExpression = $query->getModel()->getConnection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%w', starts_at) AS INTEGER)"
                : '(DAYOFWEEK(starts_at) - 1)';

            $query->whereRaw($weekdayExpression.' = ?', [(int) $this->weekday]);
        }

        if ($this->motivation !== '') {
            $query->whereLike('motivation', '%'.$this->motivation.'%');
        }

        if ($this->community_id !== 0) {
            $query->where('community_id', $this->community_id);
        }

        $schedules = MassSchedule::query()->with('community:id,name')
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))
            ->orderBy('weekday')->orderBy('starts_at')->orderBy('id')->get();

        return view('livewire.masses.index', [
            'weekdays'             => ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'],
            'schedulesByWeekday'   => $schedules->groupBy('weekday'),
            'schedulesByCommunity' => $schedules->sortBy('community.name')->groupBy('community_id'),
            'communities'          => $communities,
            'masses'               => $query->with('community:id,name')
                ->orderBy('starts_at')->orderBy('id')->paginate(10),
        ]);
    }
}
