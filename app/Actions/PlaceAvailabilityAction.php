<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\Community;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class PlaceAvailabilityAction
{
    /**
     * Reservations are the source of truth for conflicts, including refused events
     * whose reservations are still held. Status changes alone do not release space.
     *
     * @param  list<int>  $placeIds
     * @param  list<int>  $ignoredReservationIds
     * @return Builder<PlaceReservation>
     */
    public function reservations(array $placeIds, CarbonInterface $start, CarbonInterface $end, array $ignoredReservationIds = []): Builder
    {
        return PlaceReservation::query()
            ->whereIn('place_id', $placeIds)
            ->when($ignoredReservationIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $ignoredReservationIds))
            ->where('reserved_from', '<', $end)
            ->where('reserved_to', '>', $start)
            ->orderBy('reserved_from')->orderBy('id');
    }

    /**
     * @param  Collection<int, Place>  $tree
     * @return list<int>
     */
    public function relatedPlaceIds(Place $place, Collection $tree): array
    {
        $byId     = $tree->keyBy('id');
        $ids      = [$place->id];
        $parentId = $place->main_place_id;
        while ($parentId !== null && $byId->has($parentId) && ! in_array($parentId, $ids, true)) {
            $ids[]    = $parentId;
            $parentId = $byId[$parentId]->main_place_id;
        }

        $pending  = [$place->id];
        $children = $tree->groupBy('main_place_id');
        while ($pending !== []) {
            $parent = array_pop($pending);

            foreach ($children->get($parent, collect()) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[]     = $child->id;
                    $pending[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /** @return Collection<int, Place> */
    public function tree(Community $community): Collection
    {
        return $community->places()->orderBy('name')->orderBy('id')->get();
    }

    public function isAvailable(Place $place, CarbonInterface|string $start, CarbonInterface|string $end): bool
    {
        return ! $this->reservations(
            $this->relatedPlaceIds($place, $this->tree($place->community)),
            CarbonImmutable::parse($start), CarbonImmutable::parse($end),
        )->exists();
    }

    /** @return list<array{place_id: int, name: string, depth: int, date: string, periods: array}> */
    public function forDate(Community $community, string $date, User $user): array
    {
        Gate::forUser($user)->authorize('view', $community);
        $tree         = $this->tree($community);
        $start        = CarbonImmutable::parse($date)->startOfDay();
        $reservations = $this->loadReservations($tree->modelKeys(), $start, $start->addDay(), $user);
        $visibleIds   = $this->visibleEventIds($reservations, $user);
        $rows         = [];
        $visited      = [];
        $walk         = function (?int $parentId, int $depth) use (&$walk, &$rows, &$visited, $tree, $reservations, $visibleIds, $start): void {
            foreach ($tree->where('main_place_id', $parentId) as $place) {
                if (isset($visited[$place->id])) {
                    continue;
                }
                $visited[$place->id] = true;
                $rows[]              = $this->row($place, $depth, $start, $tree, $reservations, $visibleIds);
                $walk($place->id, $depth + 1);
            }
        };
        $walk(null, 0);

        foreach ($tree as $place) {
            if (! isset($visited[$place->id])) {
                $rows[] = $this->row($place, 0, $start, $tree, $reservations, $visibleIds);
            }
        }

        return $rows;
    }

    /** @return list<array{place_id: int, name: string, depth: int, date: string, periods: array}> */
    public function forPlace(Place $place, string $startDate, int $numberOfDays, User $user): array
    {
        Gate::forUser($user)->authorize('view', $place->community);
        $tree         = $this->tree($place->community);
        $start        = CarbonImmutable::parse($startDate)->startOfDay();
        $reservations = $this->loadReservations($this->relatedPlaceIds($place, $tree), $start, $start->addDays($numberOfDays), $user);
        $visibleIds   = $this->visibleEventIds($reservations, $user);
        $rows         = [];

        for ($day = 0; $day < $numberOfDays; $day++) {
            $rows[] = $this->row($place, 0, $start->addDays($day), $tree, $reservations, $visibleIds);
        }

        return $rows;
    }

    /** @param list<int> $ids @return Collection<int, PlaceReservation> */
    private function loadReservations(array $ids, CarbonImmutable $start, CarbonImmutable $end, User $user): Collection
    {
        return $this->reservations($ids, $start, $end)
            ->with(['event.group:id,name', 'mass' => function (Relation $relation) use ($user): void {
                $relation->getQuery()->whereIn('id', Mass::query()->visibleTo($user)->select('id'));
            }])->get();
    }

    /** @param Collection<int, PlaceReservation> $reservations @return list<int> */
    private function visibleEventIds(Collection $reservations, User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', Event::class);

        return Event::query()->visibleTo($user)
            ->whereKey($reservations->pluck('event_id')->filter()->unique())
            ->pluck('id')->all();
    }

    /**
     * @param  Collection<int, Place>  $tree
     * @param  Collection<int, PlaceReservation>  $reservations
     * @param  list<int>  $visibleIds
     * @return array{place_id: int, name: string, depth: int, date: string, periods: array}
     */
    private function row(Place $place, int $depth, CarbonImmutable $date, Collection $tree, Collection $reservations, array $visibleIds): array
    {
        $ids     = $this->relatedPlaceIds($place, $tree);
        $periods = [];

        foreach ($reservations as $reservation) {
            if (! in_array($reservation->place_id, $ids, true)
                || ! $reservation->reserved_from->lt($date->addDay())
                || ! $reservation->reserved_to->gt($date)) {
                continue;
            }
            $event       = $reservation->event;
            $mass        = $reservation->mass;
            $visible     = $event !== null && in_array($event->id, $visibleIds, true);
            $massVisible = $mass !== null;
            $period      = [
                'start'   => CarbonImmutable::instance($reservation->reserved_from)->max($date)->format('Y-m-d H:i:s'),
                'end'     => CarbonImmutable::instance($reservation->reserved_to)->min($date->addDay())->format('Y-m-d H:i:s'),
                'visible' => $visible || $massVisible,
                'label'   => 'Horário indisponível',
                'type'    => 'restricted',
            ];

            if ($visible) {
                $period['label']   = $event->name;
                $period['type']    = 'event';
                $period['details'] = [[
                    'type'  => 'event', 'event_id' => $event->id, 'title' => $event->name,
                    'group' => $event->group?->name, 'status' => $event->status->label(),
                    'start' => $event->starts_at->format('Y-m-d H:i:s'),
                    'end'   => $event->ends_at->format('Y-m-d H:i:s'),
                ]];
            } elseif ($massVisible) {
                $period['label']   = $mass->motivation ?: 'Missa';
                $period['type']    = 'mass';
                $period['details'] = [[
                    'type'  => 'mass', 'mass_id' => $mass->id, 'title' => $mass->motivation ?: 'Missa',
                    'start' => $mass->starts_at->format('Y-m-d H:i:s'),
                    'end'   => $mass->ends_at->format('Y-m-d H:i:s'),
                ]];
            }
            $periods[] = $period;
        }

        return ['place_id' => $place->id, 'name' => $place->name, 'depth' => $depth, 'date' => $date->toDateString(), 'periods' => $this->compose($periods)];
    }

    /**
     * Restricted occupancy takes precedence in overlaps. Adjacent equal segments
     * are merged after sanitization, so private source identities never escape.
     *
     * @param  list<array>  $periods
     * @return list<array>
     */
    private function compose(array $periods): array
    {
        $boundaries = collect($periods)->flatMap(fn (array $period): array => [$period['start'], $period['end']])->unique()->sort()->values();
        $segments   = [];

        for ($index = 0; $index < $boundaries->count() - 1; $index++) {
            $start  = $boundaries[$index];
            $end    = $boundaries[$index + 1];
            $active = collect($periods)->filter(fn (array $period): bool => $period['start'] < $end && $period['end'] > $start);

            if ($active->isEmpty()) {
                continue;
            }
            $segment = ['start' => $start, 'end' => $end, 'visible' => false, 'label' => 'Horário indisponível', 'type' => 'restricted'];

            if (! $active->contains('visible', false)) {
                $details = $active->flatMap(fn (array $period): array => $period['details'])->unique(fn (array $detail): string => json_encode($detail))->values()->all();
                $segment = ['start' => $start, 'end' => $end, 'visible' => true,
                    'label'         => count($details) === 1 ? $details[0]['title'] : 'Horário ocupado',
                    'type'          => count($details) === 1 ? $details[0]['type'] : 'multiple', 'details' => $details];
            }
            $last = array_key_last($segments);

            if ($last !== null && $segments[$last]['end'] === $start
                && array_diff_key($segments[$last], ['start' => true, 'end' => true]) === array_diff_key($segment, ['start' => true, 'end' => true])) {
                $segments[$last]['end'] = $end;
            } else {
                $segments[] = $segment;
            }
        }

        return $segments;
    }
}
