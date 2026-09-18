<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Enums\EventStatusEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Listing extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public mixed $search = '';

    #[Url(except: '')]
    public mixed $status = '';

    #[Url(except: 'upcoming')]
    public mixed $period = 'upcoming';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'period', 'type', 'scope', 'group'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Event> */
    protected function listingQuery(): Builder
    {
        Gate::authorize('viewAny', Event::class);

        $this->search = is_string($this->search) ? mb_substr(mb_trim($this->search), 0, 100) : '';
        $this->status = is_string($this->status) && EventStatusEnum::tryFrom($this->status) ? $this->status : '';
        $this->period = in_array($this->period, ['upcoming', 'past', 'all'], true) ? $this->period : 'upcoming';

        return Event::query()
            ->visibleTo(auth()->user())
            ->with(['group:id,name', 'primaryReservation:id,event_id,place_id', 'primaryReservation.place:id,name'])
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($this->period === 'upcoming', fn (Builder $query): Builder => $query->upcoming())
            ->when($this->period === 'past', fn (Builder $query): Builder => $query->past());
    }

    /**
     * @param  Builder<Event>  $query
     * @return array{events: LengthAwarePaginator, memberOnly: bool, userGroupIds: array<int, int>}
     */
    protected function listingData(Builder $query): array
    {
        $user      = auth()->user();
        $direction = $this->period === 'past' ? 'desc' : 'asc';

        return [
            'events'       => $query->orderBy('starts_at', $direction)->orderBy('id', $direction)->paginate(10),
            'memberOnly'   => $user->isMemberOnly(),
            'userGroupIds' => $user->isMemberOnly() ? $user->groups()->pluck('groups.id')->all() : [],
        ];
    }
}
