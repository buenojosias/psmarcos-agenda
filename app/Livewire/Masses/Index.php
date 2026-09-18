<?php

declare(strict_types=1);

namespace App\Livewire\Masses;

use App\Models\Event;
use Livewire\Component;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public mixed $weekday = '';

    #[Url(except: 0)]
    public mixed $community_id = 0;

    #[Url(except: '')]
    public mixed $motivation = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['weekday', 'community_id', 'motivation'], true)) {
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

        $query = Event::query()
            ->visibleTo(auth()->user())
            ->where('type', EventTypeEnum::MASS)
            ->upcoming();

        $motivations = (clone $query)->select('name')->distinct()->orderBy('name')->pluck('name');
        $communities = Community::query()->select('id', 'name')->orderBy('name')->get();

        if ($this->weekday !== '') {
            $weekdayExpression = $query->getConnection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%w', starts_at) AS INTEGER)"
                : '(DAYOFWEEK(starts_at) - 1)';

            $query->whereRaw($weekdayExpression.' = ?', [(int) $this->weekday]);
        }

        if ($this->motivation !== '') {
            $query->where('name', $this->motivation);
        }

        if ($this->community_id !== 0) {
            $query->whereHas('primaryReservation.place', fn (Builder $query): Builder => $query
                ->where('community_id', $this->community_id));
        }

        return view('livewire.masses.index', [
            'motivations' => $motivations,
            'communities' => $communities,
            'events'      => $query->with([
                'primaryReservation:id,event_id,place_id',
                'primaryReservation.place:id,community_id',
                'primaryReservation.place.community:id,name',
            ])->orderBy('starts_at')->orderBy('id')->paginate(10),
        ]);
    }
}
