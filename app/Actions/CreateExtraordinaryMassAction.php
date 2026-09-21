<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Mass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateExtraordinaryMassAction
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
            'community_id' => ['required', 'integer', 'exists:communities,id'],
            'motivation'   => ['required', 'string', 'max:255'],
            'date'         => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'starts_at'    => ['required', 'date_format:H:i'],
            'ends_at'      => ['required', 'date_format:H:i', 'after:starts_at'],
        ])->validate();

        return DB::transaction(function () use ($data, $confirmed): array {
            $places     = $this->places->handle((int) $data['community_id']);
            $occurrence = [
                'starts_at' => CarbonImmutable::parse($data['date'].' '.$data['starts_at'])->toDateTimeString(),
                'ends_at'   => CarbonImmutable::parse($data['date'].' '.$data['ends_at'])->toDateTimeString(),
            ];
            $conflicts = $this->conflicts->handle($places, [$occurrence]);

            if ($conflicts !== [] && ! $confirmed) {
                return ['data' => $data, 'conflicts' => $conflicts, 'created' => false];
            }

            Mass::create([
                'mass_schedule_id' => null,
                'community_id'     => $data['community_id'],
                'motivation'       => $data['motivation'],
                ...$occurrence,
            ]);

            return ['data' => $data, 'conflicts' => [], 'created' => true];
        });
    }
}
