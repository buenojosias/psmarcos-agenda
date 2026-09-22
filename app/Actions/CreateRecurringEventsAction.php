<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CreateRecurringEventsAction
{
    public function __construct(
        private CheckPlaceAvailabilityAction $checkPlaceAvailability,
        private EventLogAction $eventLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{
     *     date: string,
     *     starts_at: string,
     *     ends_at: string,
     *     remaining_place_ids: list<int>,
     *     status: 'available'|'conflict'|'unavailable',
     *     conflicts: list<array<string, mixed>>
     * }>  $occurrences
     * @return array{
     *     created: bool,
     *     events: list<Event>,
     *     conflicts: array<string, list<array<string, mixed>>>
     * }
     */
    public function handle(array $data, array $occurrences, User $user): array
    {
        return DB::transaction(function () use ($data, $occurrences, $user): array {
            Gate::forUser($user)->authorize('create', Event::class);

            $group = Group::query()->findOrFail($data['group_id']);
            Gate::forUser($user)->authorize('selectGroup', [Event::class, $group]);

            $confirmImmediately = (bool) $data['confirm_immediately'];

            if ($confirmImmediately) {
                Gate::forUser($user)->authorize('confirmImmediately', Event::class);
            }

            $validOccurrences = collect($occurrences)
                ->filter(fn (array $occurrence): bool => $occurrence['status'] === 'available'
                    && $occurrence['remaining_place_ids'] !== [])
                ->map(function (array $occurrence): array {
                    $occurrence['remaining_place_ids'] = collect($occurrence['remaining_place_ids'])
                        ->map(fn (mixed $placeId): int => (int) $placeId)
                        ->unique()
                        ->values()
                        ->all();

                    return $occurrence;
                });

            if ($validOccurrences->isEmpty()) {
                throw ValidationException::withMessages([
                    'dates' => 'É necessário manter ao menos uma ocorrência disponível.',
                ]);
            }

            $placeIds = $validOccurrences
                ->pluck('remaining_place_ids')
                ->flatten()
                ->map(fn (mixed $placeId): int => (int) $placeId)
                ->unique()
                ->values();
            $places = Place::query()->whereKey($placeIds)->get();

            if (
                $places->count() !== $placeIds->count()
                || $places->contains(fn (Place $place): bool => (int) $place->community_id !== (int) $data['community_id'])
            ) {
                throw ValidationException::withMessages([
                    'place_ids' => 'Todos os ambientes devem pertencer à comunidade selecionada.',
                ]);
            }

            $conflicts = [];

            foreach ($validOccurrences as $date => $occurrence) {
                $occurrencePlaceIds = collect($occurrence['remaining_place_ids'])
                    ->map(fn (mixed $placeId): int => (int) $placeId)
                    ->unique()
                    ->values();

                foreach ($occurrencePlaceIds as $placeId) {
                    if (! isset($data['place_hours'][$placeId])) {
                        throw ValidationException::withMessages([
                            'place_hours' => 'Informe as horas adicionais de todos os ambientes selecionados.',
                        ]);
                    }
                }

                /** @var Collection<int, Place> $occurrencePlaces */
                $occurrencePlaces = $places
                    ->filter(fn (Place $place): bool => $occurrencePlaceIds->contains($place->id))
                    ->values();
                $placeHours = $occurrencePlaces->mapWithKeys(function (Place $place) use ($data): array {
                    $hours = $data['place_hours'][$place->id];

                    return [$place->id => [
                        'before_hours' => $hours['before'],
                        'after_hours'  => $hours['after'],
                    ]];
                })->all();
                $occurrenceConflicts = $this->checkPlaceAvailability->handle(
                    $occurrence['starts_at'],
                    $occurrence['ends_at'],
                    $occurrencePlaces,
                    $placeHours,
                );

                if ($occurrenceConflicts !== []) {
                    $conflicts[(string) $date] = $occurrenceConflicts;
                }
            }

            if ($conflicts !== []) {
                return ['created' => false, 'events' => [], 'conflicts' => $conflicts];
            }

            $status         = $confirmImmediately ? EventStatusEnum::CONFIRMED : EventStatusEnum::PENDING;
            $recurrenceCode = (string) Str::ulid();
            $operationCode  = (string) Str::ulid();
            $detailData     = $this->detailData($data);
            $hasDetails     = $this->hasDetails($detailData);
            $events         = [];

            foreach ($validOccurrences as $occurrence) {
                $event = Event::create([
                    'created_by_user_id' => $user->id,
                    'group_id'           => $data['group_id'],
                    'name'               => $data['name'],
                    'type'               => $data['type'],
                    'recurrence_code'    => $recurrenceCode,
                    'starts_at'          => $occurrence['starts_at'],
                    'ends_at'            => $occurrence['ends_at'],
                    'status'             => $status,
                    'is_external'        => false,
                    'is_public'          => $data['is_public'],
                    'advertisable'       => false,
                ]);

                if ($hasDetails) {
                    $event->detail()->create($detailData);
                }

                $startsAt = CarbonImmutable::parse($occurrence['starts_at']);
                $endsAt   = CarbonImmutable::parse($occurrence['ends_at']);

                foreach (array_values($occurrence['remaining_place_ids']) as $index => $placeId) {
                    $hours = $data['place_hours'][$placeId];

                    $event->reservations()->create([
                        'place_id'      => $placeId,
                        'reserved_from' => $startsAt->subMinutes((int) round((float) $hours['before'] * 60)),
                        'reserved_to'   => $endsAt->addMinutes((int) round((float) $hours['after'] * 60)),
                        'is_primary'    => $index === 0,
                    ]);
                }

                $this->eventLog->handle(
                    $event,
                    EventLogActionEnum::CREATED,
                    $user,
                    toStatus: EventStatusEnum::PENDING,
                    operationCode: $operationCode,
                );

                if ($confirmImmediately) {
                    $this->eventLog->handle(
                        $event,
                        EventLogActionEnum::APPROVED,
                        $user,
                        fromStatus: EventStatusEnum::PENDING,
                        toStatus: EventStatusEnum::CONFIRMED,
                        operationCode: $operationCode,
                    );
                }

                $events[] = $event;
            }

            return ['created' => true, 'events' => $events, 'conflicts' => []];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailData(array $data): array
    {
        return [
            'subtitle'                   => filled($data['subtitle']) ? $data['subtitle'] : null,
            'description'                => filled($data['description']) ? $data['description'] : null,
            'target_audience'            => filled($data['target_audience']) ? $data['target_audience'] : null,
            'participation_instructions' => filled($data['participation_instructions']) ? $data['participation_instructions'] : null,
            'registration_required'      => $data['registration_required'],
            'registration_url'           => filled($data['registration_url']) ? $data['registration_url'] : null,
            'registration_deadline'      => filled($data['registration_deadline']) ? $data['registration_deadline'] : null,
            'participation_cost'         => filled($data['participation_cost']) ? $data['participation_cost'] : null,
            'contact_name'               => filled($data['contact_name']) ? $data['contact_name'] : null,
            'contact_phone'              => filled($data['contact_phone']) ? $data['contact_phone'] : null,
        ];
    }

    /** @param array<string, mixed> $detailData */
    private function hasDetails(array $detailData): bool
    {
        return collect($detailData)->contains(function (mixed $value, string $field): bool {
            if ($field === 'registration_required') {
                return $value === true;
            }

            if (! is_string($value)) {
                return filled($value);
            }

            $content = $field === 'description'
                ? strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5))
                : $value;

            return preg_replace('/[\s\x{00A0}]+/u', '', $content) !== '';
        });
    }
}
