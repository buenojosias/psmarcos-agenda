<?php

declare(strict_types=1);

namespace App\Livewire\Events\Forms;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Component;
use App\Enums\EventTypeEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\CreateExternalEventAction;
use Illuminate\Support\Collection as SupportCollection;

class External extends Component
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

    public function updatedAdvertisable(): void
    {
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

        $validated = $this->validate($this->rules(), $this->messages());
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
            $this->validate($this->rules(), $this->messages()),
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
            'isMemberOnly'          => $user->isMemberOnly(),
            'types'                 => EventTypeEnum::cases(),
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
            'ends_at.after' => 'O encerramento deve ser posterior ao início.',
        ];
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
