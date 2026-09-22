<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Support\Str;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateExternalEventAction
{
    public function __construct(private EventLogAction $eventLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $user): Event
    {
        return DB::transaction(function () use ($data, $user): Event {
            Gate::forUser($user)->authorize('create', Event::class);

            $group = Group::query()->findOrFail($data['group_id']);
            Gate::forUser($user)->authorize('selectGroup', [Event::class, $group]);

            $confirmImmediately = (bool) $data['confirm_immediately'];

            if ($confirmImmediately) {
                Gate::forUser($user)->authorize('confirmImmediately', Event::class);
            }

            $status = $confirmImmediately ? EventStatusEnum::CONFIRMED : EventStatusEnum::PENDING;
            $event  = Event::create([
                'created_by_user_id' => $user->id,
                'group_id'           => $data['group_id'],
                'name'               => $data['name'],
                'complement'         => filled($data['complement']) ? $data['complement'] : null,
                'type'               => $data['type'],
                'recurrence_code'    => null,
                'starts_at'          => $data['starts_at'],
                'ends_at'            => $data['ends_at'],
                'status'             => $status,
                'is_external'        => true,
                'is_public'          => $data['is_public'],
                'advertisable'       => $data['advertisable'],
            ]);

            $event->detail()->create([
                'description'                => filled($data['description']) ? $data['description'] : null,
                'target_audience'            => filled($data['target_audience']) ? $data['target_audience'] : null,
                'participation_instructions' => filled($data['participation_instructions']) ? $data['participation_instructions'] : null,
                'registration_required'      => $data['registration_required'],
                'registration_url'           => filled($data['registration_url']) ? $data['registration_url'] : null,
                'registration_deadline'      => filled($data['registration_deadline']) ? $data['registration_deadline'] : null,
                'participation_cost'         => filled($data['participation_cost']) ? $data['participation_cost'] : null,
                'contact_name'               => filled($data['contact_name']) ? $data['contact_name'] : null,
                'contact_phone'              => filled($data['contact_phone']) ? $data['contact_phone'] : null,
                'external_location_name'     => $data['external_location_name'],
                'external_location_address'  => $data['external_location_address'],
                'external_location_url'      => filled($data['external_location_url']) ? $data['external_location_url'] : null,
            ]);

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

            return $event;
        });
    }
}
