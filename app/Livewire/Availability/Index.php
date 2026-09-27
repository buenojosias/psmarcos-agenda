<?php

declare(strict_types=1);

namespace App\Livewire\Availability;

use App\Models\Mass;
use App\Models\Event;
use App\Models\Place;
use Livewire\Component;
use App\Models\Community;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\PlaceAvailabilityAction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property-read Collection<int, Community> $communities
 * @property-read Collection<int, Place> $places
 */
class Index extends Component
{
    #[Url(as: 'view', except: 'data')]
    public mixed $mode = 'data';

    #[Url(as: 'comunidade', except: '')]
    public mixed $communityId = '';

    #[Url(as: 'ambiente', except: '')]
    public mixed $placeId = '';

    #[Url(as: 'data', except: '')]
    public mixed $date = '';

    /** @var list<array{place_id: int, name: string, depth: int, date: string, periods: array}> */
    #[Locked]
    public array $rows = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $detail = null;

    #[Locked]
    public int $generation = 0;

    public bool $showDetail = false;

    private PlaceAvailabilityAction $availability;

    public function boot(PlaceAvailabilityAction $availability): void
    {
        $this->availability = $availability;
        Gate::authorize('viewAny', Community::class);
        Gate::authorize('viewAny', Event::class);
    }

    public function mount(): void
    {
        $this->resetMap();
    }

    public function hydrate(): void
    {
        $this->detail = null;
        $sources      = collect($this->rows)->flatMap(fn (array $row): array => $row['periods'])
            ->flatMap(fn (array $period): array => $period['details'] ?? []);
        $ids            = $sources->pluck('event_id')->filter()->unique();
        $massIds        = $sources->pluck('mass_id')->filter()->unique();
        $visibleIds     = Event::query()->visibleTo(auth()->user())->whereKey($ids)->pluck('id')->all();
        $visibleMassIds = Mass::query()->visibleTo(auth()->user())->whereKey($massIds)->pluck('id')->all();

        foreach ($this->rows as &$row) {
            foreach ($row['periods'] as &$period) {
                foreach ($period['details'] ?? [] as $source) {
                    if ((isset($source['event_id']) && ! in_array($source['event_id'], $visibleIds, true))
                        || (isset($source['mass_id']) && ! in_array($source['mass_id'], $visibleMassIds, true))) {
                        $period = ['start' => $period['start'], 'end' => $period['end'], 'visible' => false, 'label' => 'Horário indisponível', 'type' => 'restricted'];

                        break;
                    }
                }
            }
            unset($period);
        }
        unset($row);
    }

    /** @return Collection<int, Community> */
    #[Computed]
    public function communities(): Collection
    {
        return Community::query()->orderBy('name')->orderBy('id')->get()
            ->filter(fn (Community $community): bool => auth()->user()->can('view', $community));
    }

    /** @return Collection<int, Place> */
    #[Computed]
    public function places(): Collection
    {
        $community = $this->communities->firstWhere('id', $this->communityId);

        return $community ? $this->availability->tree($community) : new Collection;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['mode', 'communityId', 'placeId', 'date'], true)) {
            unset($this->communities, $this->places);
            $this->resetMap();
        }
    }

    public function previousDay(): void
    {
        $this->normalizeState();
        $this->date = CarbonImmutable::parse($this->date)->subDay()->toDateString();
        $this->resetMap();
    }

    public function nextDay(): void
    {
        $this->normalizeState();
        $this->date = CarbonImmutable::parse($this->date)->addDay()->toDateString();
        $this->resetMap();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
        $this->resetMap();
    }

    public function loadMore(int $expectedCount, int $generation): void
    {
        $this->normalizeState();

        if ($this->mode !== 'ambiente' || $this->placeId === ''
            || $expectedCount !== count($this->rows) || $generation !== $this->generation) {
            return;
        }
        $place      = $this->places->firstWhere('id', $this->placeId);
        $start      = CarbonImmutable::parse($this->date)->addDays(count($this->rows))->toDateString();
        $this->rows = array_merge($this->rows, $this->availability->forPlace($place, $start, 14, auth()->user()));
    }

    public function openPeriod(int $rowIndex, int $periodIndex): void
    {
        $this->normalizeState();
        $row    = $this->rows[$rowIndex] ?? null;
        $period = $row['periods'][$periodIndex] ?? null;

        if ($period === null) {
            return;
        }
        $place = $this->places->firstWhere('id', $row['place_id']);

        if ($place === null || ($this->mode === 'ambiente' && (int) $this->placeId !== $place->id)) {
            return;
        }
        $fresh  = $this->availability->forPlace($place, $row['date'], 1, auth()->user())[0];
        $period = collect($fresh['periods'])->first(fn (array $candidate): bool => $candidate['start'] <= $period['start'] && $candidate['end'] > $period['start']);

        if ($period === null) {
            $this->resetMap();

            return;
        }
        $this->detail     = $period + ['date' => $row['date'], 'place' => $place->name, 'community' => $this->communities->firstWhere('id', $this->communityId)->name];
        $this->showDetail = true;
    }

    public function render(): View
    {
        return view('livewire.availability.index');
    }

    private function normalizeState(): void
    {
        $this->mode = in_array($this->mode, ['data', 'ambiente'], true) ? $this->mode : 'data';

        if (! is_string($this->date) || Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:9998-01-01']])->fails()) {
            $this->date = today()->toDateString();
        }
        $communityId       = filter_var($this->communityId, FILTER_VALIDATE_INT);
        $this->communityId = $communityId && $this->communities->contains('id', $communityId)
            ? (string) $communityId : (string) ($this->communities->first()->id ?? '');
        unset($this->places);
        $placeId       = filter_var($this->placeId, FILTER_VALIDATE_INT);
        $this->placeId = $placeId && $this->places->contains('id', $placeId) ? (string) $placeId : '';
    }

    private function resetMap(): void
    {
        $this->normalizeState();
        $this->rows       = [];
        $this->detail     = null;
        $this->showDetail = false;
        $this->generation++;
        $community = $this->communities->firstWhere('id', $this->communityId);

        if ($community !== null) {
            if ($this->mode === 'data') {
                $this->rows = $this->availability->forDate($community, $this->date, auth()->user());
            } elseif ($this->placeId !== '') {
                $this->rows = $this->availability->forPlace($this->places->firstWhere('id', $this->placeId), $this->date, 14, auth()->user());
            }
        }
        $this->dispatch('availability-reset');
    }
}
