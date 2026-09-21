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

class CreateEventAction
{
    public function __construct(
        private CheckPlaceAvailabilityAction $checkPlaceAvailability,
        private EventLogAction $eventLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     created: bool,
     *     event: Event|null,
     *     conflicts: list<array<string, mixed>>
     * }
     */
    public function handle(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user): array {
            Gate::forUser($user)->authorize('create', Event::class);

            $group = Group::query()->findOrFail($data['group_id']);
            Gate::forUser($user)->authorize('selectGroup', [Event::class, $group]);

            $confirmImmediately = (bool) $data['confirm_immediately'];

            if ($confirmImmediately) {
                Gate::forUser($user)->authorize('confirmImmediately', Event::class);
            }

            $places     = Place::query()->whereKey($data['place_ids'])->get();
            $placeHours = $places->mapWithKeys(function (Place $place) use ($data): array {
                $hours = $data['place_hours'][$place->id];

                return [$place->id => [
                    'before_hours' => $hours['before'],
                    'after_hours'  => $hours['after'],
                ]];
            })->all();
            $conflicts = $this->checkPlaceAvailability->handle(
                $data['starts_at'],
                $data['ends_at'],
                $places,
                $placeHours,
            );

            if ($conflicts !== []) {
                return ['created' => false, 'event' => null, 'conflicts' => $conflicts];
            }

            $status = $confirmImmediately ? EventStatusEnum::CONFIRMED : EventStatusEnum::PENDING;
            $event  = Event::create([
                'group_id'        => $data['group_id'],
                'name'            => $data['name'],
                'type'            => $data['type'],
                'recurrence_code' => null,
                'starts_at'       => $data['starts_at'],
                'ends_at'         => $data['ends_at'],
                'status'          => $status,
                'is_external'     => false,
                'is_public'       => $data['is_public'],
                'advertisable'    => $data['advertisable'],
            ]);

            $event->detail()->create([
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
            ]);

            $startsAt = CarbonImmutable::parse($data['starts_at']);
            $endsAt   = CarbonImmutable::parse($data['ends_at']);

            foreach (array_values($data['place_ids']) as $index => $placeId) {
                $hours = $data['place_hours'][$placeId];

                $event->reservations()->create([
                    'place_id'      => $placeId,
                    'reserved_from' => $startsAt->subMinutes((int) round((float) $hours['before'] * 60)),
                    'reserved_to'   => $endsAt->addMinutes((int) round((float) $hours['after'] * 60)),
                    'is_primary'    => $index === 0,
                ]);
            }

            $operationCode = (string) Str::ulid();
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

            return ['created' => true, 'event' => $event, 'conflicts' => []];
        });
    }
}
