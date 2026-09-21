<?php

declare(strict_types=1);

namespace App\Livewire\Events\Forms;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use Livewire\Component;
use App\Models\Community;
use Carbon\CarbonImmutable;
use App\Enums\EventTypeEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\CreateRecurringEventsAction;
use Illuminate\Database\Eloquent\Collection;
use App\Actions\CheckPlaceAvailabilityAction;
use Illuminate\Support\Collection as SupportCollection;

class Recurring extends Component
{
    use Alert;

    public mixed $group_id = '';

    public string $name = '';

    public mixed $type = '';

    /** @var list<string> */
    public array $dates = [];

    public string $starts_time = '';

    public string $ends_time = '';

    public bool $is_public = false;

    public bool $confirm_immediately = false;

    public string $subtitle = '';

    public string $description = '';

    public string $target_audience = '';

    public string $participation_instructions = '';

    public bool $registration_required = false;

    public string $registration_url = '';

    public string $registration_deadline = '';

    public string $participation_cost = '';

    public string $contact_name = '';

    public string $contact_phone = '';

    public mixed $community_id = '';

    /** @var list<int> */
    public array $place_ids = [];

    /** @var array<int, array{before: float|int|string, after: float|int|string}> */
    public array $place_hours = [];

    /**
     * @var array<string, array{
     *     date: string,
     *     starts_at: string,
     *     ends_at: string,
     *     remaining_place_ids: list<int>,
     *     status: 'available'|'conflict'|'unavailable',
     *     conflicts: list<array<string, mixed>>
     * }>
     */
    #[Locked]
    public array $occurrences = [];

    #[Locked]
    public bool $draftValidated = false;

    public bool $conflictsSlide = false;

    public function mount(): void
    {
        Gate::authorize('create', Event::class);
    }

    public function updatedCommunityId(): void
    {
        $this->place_ids   = [];
        $this->place_hours = [];
    }

    public function updated(string $property): void
    {
        if ($property !== 'conflictsSlide') {
            $this->draftValidated = false;
        }
    }

    public function updatedConfirmImmediately(bool $value): void
    {
        if ($value && Gate::denies('confirmImmediately', Event::class)) {
            $this->confirm_immediately = false;
        }
    }

    public function updatedPlaceIds(mixed $value): void
    {
        $requestedIds = collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $availableIds = Place::query()
            ->where('community_id', $this->community_id)
            ->whereKey($requestedIds)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        $this->place_ids   = $requestedIds->filter(fn (int $id): bool => in_array($id, $availableIds, true))->all();
        $this->place_hours = collect($this->place_ids)->mapWithKeys(fn (int $id): array => [$id => [
            'before' => $this->place_hours[$id]['before'] ?? 0,
            'after'  => $this->place_hours[$id]['after'] ?? 0,
        ]])->all();
    }

    public function validateDraft(CheckPlaceAvailabilityAction $checkPlaceAvailability): void
    {
        Gate::authorize('create', Event::class);

        $this->dates = collect($this->dates)
            ->filter(fn (mixed $date): bool => is_string($date))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $this->occurrences    = [];
        $this->conflictsSlide = false;
        $this->draftValidated = false;

        $validated = $this->validate($this->rules(), $this->messages());
        $group     = Group::query()->find($validated['group_id']);

        Gate::authorize('selectGroup', [Event::class, $group]);

        if ($validated['confirm_immediately']) {
            Gate::authorize('confirmImmediately', Event::class);
        }

        $selectedPlaces = Place::query()->whereKey($validated['place_ids'])->get();
        $placeHours     = $selectedPlaces->mapWithKeys(function (Place $place) use ($validated): array {
            $hours = $validated['place_hours'][$place->id] ?? ['before' => 0, 'after' => 0];

            return [$place->id => [
                'before_hours' => $hours['before'],
                'after_hours'  => $hours['after'],
            ]];
        })->all();

        foreach ($validated['dates'] as $date) {
            $startsAt  = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$validated['starts_time']);
            $endsAt    = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$validated['ends_time']);
            $conflicts = $checkPlaceAvailability->handle($startsAt, $endsAt, $selectedPlaces, $placeHours);

            $this->occurrences[$date] = [
                'date'                => $date,
                'starts_at'           => $startsAt->format('Y-m-d H:i:s'),
                'ends_at'             => $endsAt->format('Y-m-d H:i:s'),
                'remaining_place_ids' => $selectedPlaces->modelKeys(),
                'status'              => $conflicts === [] ? 'available' : 'conflict',
                'conflicts'           => $conflicts,
            ];
        }

        if (collect($this->occurrences)->contains('status', 'conflict')) {
            $this->conflictsSlide = true;

            return;
        }

        $this->showConfirmationDialog();
    }

    public function removeConflictingPlaces(string $date): void
    {
        Gate::authorize('create', Event::class);

        if (($this->occurrences[$date]['status'] ?? null) !== 'conflict') {
            return;
        }

        $conflictingPlaceIds = collect($this->occurrences[$date]['conflicts'])
            ->pluck('requested_place.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $remainingPlaceIds = collect($this->occurrences[$date]['remaining_place_ids'])
            ->reject(fn (int $id): bool => in_array($id, $conflictingPlaceIds, true))
            ->values()
            ->all();

        $this->occurrences[$date]['remaining_place_ids'] = $remainingPlaceIds;
        $this->occurrences[$date]['status']              = $remainingPlaceIds === [] ? 'unavailable' : 'available';

        if (! collect($this->occurrences)->contains('status', 'conflict') && $this->validOccurrences() !== []) {
            $this->conflictsSlide = false;
            $this->showConfirmationDialog();
        }
    }

    public function save(CreateRecurringEventsAction $createRecurringEvents): void
    {
        if (! $this->draftValidated) {
            return;
        }

        $user = user();
        abort_if($user === null, 401);

        $validated = $this->validate($this->rules(), $this->messages());
        $result    = $createRecurringEvents->handle($validated, $this->occurrences, $user);

        if (! $result['created']) {
            foreach ($this->occurrences as $date => $occurrence) {
                if ($occurrence['status'] === 'unavailable') {
                    continue;
                }

                $conflicts = $result['conflicts'][$date] ?? [];

                $this->occurrences[$date]['conflicts'] = $conflicts;
                $this->occurrences[$date]['status']    = $conflicts === [] ? 'available' : 'conflict';
            }

            $this->draftValidated = false;
            $this->conflictsSlide = true;

            return;
        }

        $createdCount = count($result['events']);

        $this->reset();

        $this->toast()
            ->success('Eventos cadastrados', $createdCount === 1
                ? 'O evento recorrente foi cadastrado com sucesso.'
                : "As {$createdCount} ocorrências foram cadastradas com sucesso.")
            ->flash()
            ->send();

        $this->redirectRoute('events.index');
    }

    public function render(): View
    {
        Gate::authorize('create', Event::class);

        $user = auth()->user();

        return view('livewire.events.forms.recurring', [
            'canConfirmImmediately' => Gate::allows('confirmImmediately', Event::class),
            'groups'                => $this->groups($user),
            'isMemberOnly'          => $user->isMemberOnly(),
            'communities'           => Community::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn (Community $community): array => ['label' => $community->name, 'value' => $community->id]),
            'places'         => $this->places(),
            'selectedPlaces' => $this->selectedPlaces(),
            'types'          => EventTypeEnum::cases(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'group_id'                   => ['required', 'integer', Rule::exists('groups', 'id')],
            'name'                       => ['required', 'string', 'max:255'],
            'type'                       => ['required', Rule::enum(EventTypeEnum::class)],
            'dates'                      => ['required', 'array', 'min:2'],
            'dates.*'                    => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'starts_time'                => ['required', 'date_format:H:i'],
            'ends_time'                  => ['required', 'date_format:H:i', 'after:starts_time'],
            'is_public'                  => ['boolean'],
            'confirm_immediately'        => ['boolean'],
            'subtitle'                   => ['nullable', 'string', 'max:255'],
            'description'                => ['nullable', 'string'],
            'target_audience'            => ['nullable', 'string', 'max:255'],
            'participation_instructions' => ['nullable', 'string'],
            'registration_required'      => ['boolean'],
            'registration_url'           => ['nullable', 'url', 'max:255'],
            'registration_deadline'      => ['nullable', 'date_format:Y-m-d'],
            'participation_cost'         => ['nullable', 'string', 'max:255'],
            'contact_name'               => ['nullable', 'string', 'max:255'],
            'contact_phone'              => ['nullable', 'string', 'max:20'],
            'community_id'               => ['required', 'integer', Rule::exists('communities', 'id')],
            'place_ids'                  => ['required', 'array', 'min:1'],
            'place_ids.*'                => ['integer', Rule::exists('places', 'id')->where('community_id', $this->community_id)],
            'place_hours'                => ['array'],
            'place_hours.*.before'       => ['required', 'numeric', 'min:0', 'multiple_of:0.25'],
            'place_hours.*.after'        => ['required', 'numeric', 'min:0', 'multiple_of:0.25'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'dates.min'              => 'Selecione pelo menos duas datas.',
            'dates.*.after_or_equal' => 'As datas devem ser hoje ou futuras.',
            'ends_time.after'        => 'O término deve ser posterior ao início.',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function validOccurrences(): array
    {
        return collect($this->occurrences)
            ->filter(fn (array $occurrence): bool => $occurrence['status'] === 'available'
                && $occurrence['remaining_place_ids'] !== [])
            ->all();
    }

    private function showConfirmationDialog(): void
    {
        $count       = count($this->validOccurrences());
        $description = $count === 1
            ? '1 ocorrência será criada.'
            : "{$count} ocorrências serão criadas.";

        $this->draftValidated = true;
        $this->dialog()
            ->success('Confirmar cadastro', $description)
            ->confirm('Salvar eventos', 'save')
            ->cancel('Alterar informações')
            ->send();
    }

    /** @return SupportCollection<int, array{label: string, value: int}> */
    private function places(): SupportCollection
    {
        if (! filled($this->community_id)) {
            return collect();
        }

        return Place::query()
            ->with('main:id,name')
            ->where('community_id', $this->community_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Place $place): array => [
                'label' => $place->main === null ? $place->name : $place->main->name.': '.$place->name,
                'value' => $place->id,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return Collection<int, Place> */
    private function selectedPlaces(): Collection
    {
        return Place::query()->with('main:id,name')->whereKey($this->place_ids)->orderBy('name')->orderBy('id')->get();
    }

    /** @return SupportCollection<int, array{label: string, value: list<array{label: string, value: int}>}> */
    private function groups(User $user): SupportCollection
    {
        $groups = Group::query()
            ->with('community:id,name')
            ->when($user->isMemberOnly(), fn ($query) => $query->whereIn('id', $user->groups()->select('groups.id')))
            ->orderBy('name')
            ->get(['id', 'community_id', 'name']);

        $ungrouped = $groups->whereNull('community_id');
        $grouped   = $groups->whereNotNull('community_id')
            ->groupBy(fn (Group $group): string => $group->community->name)
            ->sortKeys()
            ->map(fn (SupportCollection $groups, string $community): array => [
                'label' => $community,
                'value' => $this->groupOptions($groups),
            ]);

        if ($ungrouped->isNotEmpty()) {
            $grouped->prepend([
                'label' => 'Sem comunidade',
                'value' => $this->groupOptions($ungrouped),
            ]);
        }

        return $grouped->values();
    }

    /** @return list<array{label: string, value: int}> */
    private function groupOptions(SupportCollection $groups): array
    {
        return $groups->map(fn (Group $group): array => ['label' => $group->name, 'value' => $group->id])->values()->all();
    }
}
