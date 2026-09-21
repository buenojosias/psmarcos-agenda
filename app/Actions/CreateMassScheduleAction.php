<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Mass;
use Carbon\CarbonImmutable;
use App\Models\MassSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateMassScheduleAction
{
    public function __construct(
        private ResolveMassPlacesAction $places,
        private CheckMassConflictsAction $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, conflicts: list<array{date: string, places: list<string>}>, created: bool}
     */
    public function handle(array $data, bool $confirmed = false): array
    {
        Gate::authorize('create', Mass::class);
        $data = Validator::make($data, [
            'community_id'     => ['required', 'integer', 'exists:communities,id'],
            'weekday'          => ['required', 'integer', 'between:0,6'],
            'starts_at'        => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:1,65535'],
            'motivation'       => ['nullable', 'string', 'max:255'],
            'valid_from'       => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'valid_until'      => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ])->validate();
        $data['motivation'] = blank($data['motivation']) ? null : $data['motivation'];

        $first              = CarbonImmutable::parse($data['valid_from']);
        $first              = $first->addDays(((int) $data['weekday'] - $first->dayOfWeek + 7) % 7);
        $data['valid_from'] = $first->toDateString();

        if ($first->gt(CarbonImmutable::parse($data['valid_until']))) {
            throw ValidationException::withMessages(['valid_until' => 'A data final deve ser igual ou posterior à primeira ocorrência ('.$first->format('d/m/Y').').']);
        }

        return DB::transaction(function () use ($data, $confirmed): array {
            $places      = $this->places->handle((int) $data['community_id']);
            $schedule    = new MassSchedule($data);
            $occurrences = $schedule->occurrences();
            $conflicts   = $this->conflicts->handle($places, $occurrences);

            if ($conflicts !== [] && ! $confirmed) {
                return ['data' => $data, 'conflicts' => $conflicts, 'created' => false];
            }

            $schedule->save();

            foreach ($occurrences as $occurrence) {
                $schedule->generateMassForDate(CarbonImmutable::parse($occurrence['starts_at']));
            }

            return ['data' => $data, 'conflicts' => [], 'created' => true];
        });
    }
}
