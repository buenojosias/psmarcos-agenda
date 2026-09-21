<?php

declare(strict_types=1);

namespace App\Livewire\Events\Forms;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use Livewire\Component;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use App\Actions\CreateEventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Collection;
use App\Actions\CheckPlaceAvailabilityAction;
use Illuminate\Support\Collection as SupportCollection;

class Occasional extends Component
{
    use Alert;

    public mixed $group_id = '';

    public string $name = '';

    public mixed $type = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public bool $is_public = false;

    public bool $advertisable = false;

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

    public bool $draftValidated = false;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $placeConflicts = [];

    public bool $conflictsSlide = false;

    public function mount(): void
    {
        Gate::authorize('create', Event::class);
    }

    public function updatedAdvertisable(): void
    {
        $this->draftValidated = false;
    }

    public function updatedCommunityId(): void
    {
        $this->place_ids      = [];
        $this->place_hours    = [];
        $this->draftValidated = false;
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
        $this->draftValidated = false;
    }

    public function validateDraft(
        CheckPlaceAvailabilityAction $checkPlaceAvailability,
        CreateEventAction $createEvent,
    ): void {
        Gate::authorize('create', Event::class);
        $this->draftValidated = false;
        $this->conflictsSlide = false;
        $this->placeConflicts = [];

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

        $this->placeConflicts = $checkPlaceAvailability->handle(
            $validated['starts_at'],
            $validated['ends_at'],
            $selectedPlaces,
            $placeHours,
        );

        if ($this->placeConflicts !== []) {
            $this->conflictsSlide = true;

            return;
        }

        $this->draftValidated = true;
        $this->save($createEvent);
    }

    public function adjustConflicts(): void
    {
        $this->conflictsSlide = false;
        $this->placeConflicts = [];
        $this->draftValidated = false;
        $this->resetErrorBag('place_ids');
    }

    public function continueWithoutConflictingPlaces(): void
    {
        Gate::authorize('create', Event::class);

        if ($this->placeConflicts === []) {
            $this->draftValidated = false;
            $this->addError('place_ids', 'Valide novamente os ambientes e horários antes de continuar.');

            return;
        }

        $conflictingPlaceIds = collect($this->placeConflicts)
            ->pluck('requested_place.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $remainingPlaceIds = collect($this->place_ids)
            ->reject(fn (int $id): bool => in_array($id, $conflictingPlaceIds, true))
            ->values()
            ->all();

        if ($remainingPlaceIds === []) {
            $this->addError('place_ids', 'Todos os ambientes selecionados possuem conflito. Volte e ajuste os ambientes ou horários.');

            return;
        }

        $this->place_ids   = $remainingPlaceIds;
        $this->place_hours = collect($remainingPlaceIds)->mapWithKeys(fn (int $id): array => [
            $id => $this->place_hours[$id] ?? ['before' => 0, 'after' => 0],
        ])->all();
        $this->placeConflicts = [];
        $this->conflictsSlide = false;
        $this->draftValidated = true;
        $this->resetErrorBag('place_ids');
    }

    public function save(CreateEventAction $createEvent): void
    {
        if (! $this->draftValidated) {
            return;
        }

        $user = user();
        abort_if($user === null, 401);

        $validated = $this->validate($this->rules(), $this->messages());
        $result    = $createEvent->handle($validated, $user);

        if (! $result['created']) {
            $this->placeConflicts = $result['conflicts'];
            $this->conflictsSlide = true;
            $this->draftValidated = false;

            return;
        }

        $this->toast()
            ->success('Evento cadastrado', 'O evento foi cadastrado com sucesso.')
            ->flash()
            ->send();

        $this->redirectRoute('events.show', ['event' => $result['event']]);
    }

    public function render(): View
    {
        Gate::authorize('create', Event::class);

        $user = auth()->user();

        return view('livewire.events.forms.occasional', [
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

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'group_id'                   => ['required', 'integer', Rule::exists('groups', 'id')],
            'name'                       => ['required', 'string', 'max:255'],
            'type'                       => ['required', Rule::enum(EventTypeEnum::class)],
            'starts_at'                  => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at'                    => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'is_public'                  => ['boolean'],
            'advertisable'               => ['boolean'],
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
            'community_id'               => ['nullable', 'integer', Rule::exists('communities', 'id')],
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
            'ends_at.after' => 'O término deve ser posterior ao início.',
        ];
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
        return $groups->map(fn (Group $group): array => [
            'label' => $group->name,
            'value' => $group->id,
        ])->values()->all();
    }
}
