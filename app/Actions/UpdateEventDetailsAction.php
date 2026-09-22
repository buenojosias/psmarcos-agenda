<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use DateTimeInterface;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateEventDetailsAction
{
    /** @var list<string> */
    private const EDITABLE_FIELDS = [
        'subtitle',
        'description',
        'target_audience',
        'participation_instructions',
        'registration_required',
        'registration_url',
        'registration_deadline',
        'participation_cost',
        'contact_name',
        'contact_phone',
    ];

    public function __construct(private EventLogAction $eventLogAction) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Event $event, array $data, User $user): bool
    {
        return DB::transaction(function () use ($event, $data, $user): bool {
            $event->refresh();

            Gate::forUser($user)->authorize('update', $event);

            if ($event->status === EventStatusEnum::CANCELED) {
                throw new AuthorizationException('Eventos cancelados não podem ser alterados.');
            }

            $detail    = $event->detail()->first();
            $oldValues = $this->normalizedCurrentValues($detail?->getAttributes() ?? []);
            $newValues = $oldValues;

            foreach (self::EDITABLE_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $newValues[$field] = $this->normalizeValue($field, $data[$field]);
                }
            }

            $changes = [];

            foreach (self::EDITABLE_FIELDS as $field) {
                if ($oldValues[$field] !== $newValues[$field]) {
                    $changes[$field] = [
                        'old' => $oldValues[$field],
                        'new' => $newValues[$field],
                    ];
                }
            }

            if ($changes === []) {
                return false;
            }

            $hasFilledDetail = collect($newValues)->contains(
                static fn (mixed $value): bool => $value !== null && $value !== false,
            );
            $persistenceValues                          = $newValues;
            $persistenceValues['registration_required'] = $newValues['registration_required'] ?? false;

            if ($hasFilledDetail) {
                $event->detail()->updateOrCreate([], $persistenceValues);
            } elseif ($detail !== null && ! $event->is_external) {
                $detail->delete();
            } elseif ($detail !== null) {
                $detail->update($persistenceValues);
            }

            if ($event->status !== EventStatusEnum::PENDING) {
                $this->eventLogAction->handle(
                    event: $event,
                    action: EventLogActionEnum::UPDATED,
                    user: $user,
                    changes: $changes,
                );
            }

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizedCurrentValues(array $attributes): array
    {
        $values = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $values[$field] = $this->normalizeValue($field, $attributes[$field] ?? null);
        }

        return $values;
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if ($field === 'registration_required') {
            return (bool) $value;
        }

        if ($field === 'registration_deadline' && $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = mb_trim($value);

        if ($value === '') {
            return null;
        }

        if ($field === 'description' && $this->isVisuallyEmptyHtml($value)) {
            return null;
        }

        return $value;
    }

    private function isVisuallyEmptyHtml(string $value): bool
    {
        $visibleText = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $visibleText = str_replace("\u{00A0}", '', $visibleText);

        return preg_replace('/\s+/u', '', $visibleText) === '';
    }
}
