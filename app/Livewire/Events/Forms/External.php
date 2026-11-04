<?php

declare(strict_types=1);

namespace App\Livewire\Events\Forms;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use Carbon\CarbonImmutable;
use App\Enums\EventTypeEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\CreateExternalEventAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection as SupportCollection;

class External extends Component
{
    use Alert;

    public mixed $group_id = '';

    public string $name = '';

    public mixed $type = '';

    public ?string $starts_date = '';

    public ?string $starts_time = '';

    public ?string $ends_date = '';

    public ?string $ends_time = '';

    public bool $endingTimeIsAutomatic = false;

    public bool $endingDateIsAutomatic = false;

    public bool $is_public = false;

    public bool $advertisable = false;

    public bool $confirm_immediately = false;

    public string $external_location_name = '';

    public string $external_location_address = '';

    public string $external_location_url = '';

    public string $complement = '';

    public string $description = '';

    public string $target_audience = '';

    public string $participation_instructions = '';

    public bool $registration_required = false;

    public string $registration_url = '';

    public string $registration_deadline = '';

    public string $participation_cost = '';

    public string $contact_name = '';

    public string $contact_phone = '';

    public bool $draftValidated = false;

    public function mount(): void
    {
        Gate::authorize('create', Event::class);
    }

    public function updatedStartsDate(): void
    {
        $this->draftValidated = false;

        if (blank($this->starts_date)) {
            return;
        }

        if ($this->endingDateIsAutomatic) {
            $this->ends_date = CarbonImmutable::parse($this->starts_date)->addDay()->toDateString();

            return;
        }

        if (blank($this->ends_date) || $this->ends_date < $this->starts_date) {
            $this->ends_date = $this->starts_date;
        }
    }

    public function updatedStartsTime(): void
    {
        if ((blank($this->ends_time) || $this->endingTimeIsAutomatic) && $this->starts_time !== null && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $this->starts_time) === 1) {
            $end                         = CarbonImmutable::parse('2000-01-01 '.$this->starts_time)->addHour();
            $this->ends_time             = $end->format('H:i');
            $this->endingTimeIsAutomatic = true;

            if (filled($this->starts_date)) {
                if ($end->day !== 1 && (blank($this->ends_date) || $this->ends_date <= $this->starts_date || $this->endingDateIsAutomatic)) {
                    $this->ends_date             = CarbonImmutable::parse($this->starts_date)->addDay()->toDateString();
                    $this->endingDateIsAutomatic = true;
                } elseif ($end->day === 1 && $this->endingDateIsAutomatic) {
                    $this->ends_date             = $this->starts_date;
                    $this->endingDateIsAutomatic = false;
                }
            }
        }

        $this->draftValidated = false;
    }

    public function updatedEndsTime(): void
    {
        $this->endingTimeIsAutomatic = false;

        if ($this->endingDateIsAutomatic && filled($this->starts_date) && (blank($this->ends_time) || (filled($this->starts_time) && $this->ends_time > $this->starts_time))) {
            $this->ends_date = $this->starts_date;
        }

        $this->endingDateIsAutomatic = false;
        $this->draftValidated        = false;
    }

    public function updatedEndsDate(): void
    {
        $this->endingDateIsAutomatic = false;
        $this->draftValidated        = false;
    }

    public function updatedAdvertisable(): void
    {
        if ($this->advertisable) {
            $this->is_public = true;
        }

        $this->draftValidated = false;
    }

    public function updatedIsPublic(): void
    {
        if (! $this->is_public) {
            $this->advertisable = false;
        }

        $this->draftValidated = false;
    }

    public function updatedConfirmImmediately(bool $value): void
    {
        if ($value && Gate::denies('confirmImmediately', Event::class)) {
            $this->confirm_immediately = false;
        }
    }

    public function validateDraft(): void
    {
        Gate::authorize('create', Event::class);
        $this->draftValidated = false;

        $validated = $this->validatedEventData();
        $group     = Group::query()->find($validated['group_id']);

        Gate::authorize('selectGroup', [Event::class, $group]);

        if ($validated['confirm_immediately']) {
            Gate::authorize('confirmImmediately', Event::class);
        }

        $this->draftValidated = true;
        $this->dialog()
            ->success('Dados válidos', 'Os dados estão válidos.')
            ->confirm('Salvar evento', 'save')
            ->cancel('Alterar informações')
            ->send();
    }

    public function save(CreateExternalEventAction $createExternalEvent): void
    {
        if (! $this->draftValidated) {
            return;
        }

        $user = user();
        abort_if($user === null, 401);

        $event = $createExternalEvent->handle(
            $this->validatedEventData(),
            $user,
        );

        $this->reset();

        $this->toast()
            ->success('Evento cadastrado', 'O evento foi cadastrado com sucesso.')
            ->flash()
            ->send();

        $this->redirectRoute('events.show', ['event' => $event]);
    }

    public function render(): View
    {
        Gate::authorize('create', Event::class);

        $user = auth()->user();

        return view('livewire.events.forms.external', [
            'canConfirmImmediately' => Gate::allows('confirmImmediately', Event::class),
            'groups'                => $this->groups($user),
            'canSearchGroups'       => $user->hasAnyRole([
                UserRoleEnum::CPP->value,
                UserRoleEnum::SECRETARY->value,
                UserRoleEnum::PRIEST->value,
                UserRoleEnum::ADMIN->value,
            ]),
            'types' => EventTypeEnum::cases(),
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
            'starts_date'                => ['required', 'date_format:Y-m-d'],
            'starts_time'                => ['required', 'date_format:H:i'],
            'ends_date'                  => ['required', 'date_format:Y-m-d'],
            'ends_time'                  => ['required', 'date_format:H:i'],
            'is_public'                  => ['boolean', 'accepted_if:advertisable,true'],
            'advertisable'               => ['boolean'],
            'confirm_immediately'        => ['boolean'],
            'external_location_name'     => ['required', 'string', 'max:255'],
            'external_location_address'  => ['required', 'string', 'max:255'],
            'external_location_url'      => ['nullable', 'url', 'max:255'],
            'complement'                 => ['nullable', 'string', 'max:255'],
            'description'                => ['nullable', 'string'],
            'target_audience'            => ['nullable', 'string', 'max:255'],
            'participation_instructions' => ['nullable', 'string'],
            'registration_required'      => ['boolean'],
            'registration_url'           => ['nullable', 'url', 'max:255'],
            'registration_deadline'      => ['nullable', 'date_format:Y-m-d'],
            'participation_cost'         => ['nullable', 'string', 'max:255'],
            'contact_name'               => ['nullable', 'string', 'max:255'],
            'contact_phone'              => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'is_public.accepted_if' => 'Um evento com divulgação solicitada deve ser público.',
        ];
    }

    /** @return array<string, mixed> */
    private function validatedEventData(): array
    {
        $validated = $this->validate($this->rules(), $this->messages());
        $startsAt  = CarbonImmutable::parse($validated['starts_date'].' '.$validated['starts_time']);
        $endsAt    = CarbonImmutable::parse($validated['ends_date'].' '.$validated['ends_time']);

        $now = CarbonImmutable::now();

        if ($startsAt->lessThanOrEqualTo($now)) {
            throw ValidationException::withMessages([
                $startsAt->lessThan($now->startOfDay()) ? 'starts_date' : 'starts_time' => 'O início deve ser posterior ao momento atual.',
            ]);
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_time' => 'O encerramento deve ser posterior ao início.',
            ]);
        }

        $validated['starts_at'] = $startsAt->format('Y-m-d H:i:s');
        $validated['ends_at']   = $endsAt->format('Y-m-d H:i:s');

        return $validated;
    }

    /** @return SupportCollection<int, array{label: string, value: list<array{label: string, value: int}>}> */
    private function groups(User $user): SupportCollection
    {
        $groups = Group::query()
            ->with('community:id,name')
            ->when($user->isMemberOnly(), fn ($query) => $query->whereIn('id', $user->groups()->select('groups.id')))
            ->orderBy('name')
            ->get(['id', 'community_id', 'name', 'abbreviation']);

        $ungrouped = $groups->whereNull('community_id');
        $grouped   = $groups->whereNotNull('community_id')
            ->groupBy('community_id')
            ->sortKeys(SORT_NUMERIC)
            ->map(fn (SupportCollection $groups): array => [
                'label' => $groups->first()->community->name,
                'value' => $this->groupOptions($groups),
            ]);

        if ($ungrouped->isNotEmpty()) {
            $grouped->push([
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
            'label' => $group->name.($group->abbreviation ? ' ('.$group->abbreviation.')' : ''),
            'value' => $group->id,
        ])->values()->all();
    }
}
