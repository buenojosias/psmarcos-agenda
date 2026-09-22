<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Arr;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateEventGeneralAction
{
    public function __construct(private EventLogAction $eventLogAction) {}

    /** @param array<string, mixed> $validated */
    public function handle(Event $event, array $validated, User $user): bool
    {
        return DB::transaction(function () use ($event, $validated, $user): bool {
            Gate::authorize('update', $event);
            $event->refresh();
            abort_if($event->status === EventStatusEnum::CANCELED, 403);

            $attributes = Arr::only($validated, [
                'name',
                'type',
                'is_public',
                'advertisable',
            ]);

            if ($event->recurrence_code !== null) {
                unset($attributes['advertisable']);
            }

            $event->fill($attributes);
            $dirty = $event->getDirty();

            if ($dirty === []) {
                return false;
            }

            $changes = collect(array_keys($dirty))->mapWithKeys(fn (string $field): array => [
                $field => [
                    'old' => $this->normalizeChangeValue($field, $event->getRawOriginal($field)),
                    'new' => $this->normalizeChangeValue($field, $event->getAttribute($field)),
                ],
            ])->all();

            $event->save();

            $this->eventLogAction->handle(
                event: $event,
                action: EventLogActionEnum::UPDATED,
                user: $user,
                changes: $changes,
            );

            return true;
        });
    }

    private function normalizeChangeValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'type'                      => $value instanceof EventTypeEnum ? $value->value : (string) $value,
            'is_public', 'advertisable' => (bool) $value,
            default                     => $value,
        };
    }
}
