<?php

declare(strict_types=1);

namespace App\Livewire\Events\Edit;

use App\Models\Event;
use Livewire\Component;
use App\Enums\EventTypeEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\UpdateEventGeneralAction;

class General extends Component
{
    use Alert;

    public Event $event;

    public string $name = '';

    public mixed $type = '';

    public bool $is_public = false;

    public bool $advertisable = false;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);

        $this->event = $event;
        $this->loadEventValues();
    }

    public function save(UpdateEventGeneralAction $updateEventGeneral): void
    {
        Gate::authorize('update', $this->event);

        $user = user();
        abort_if($user === null, 401);

        $changed = $updateEventGeneral->handle($this->event, $this->validate($this->rules()), $user);

        $this->event->refresh();
        $this->loadEventValues();

        if (! $changed) {
            $this->toast()->info('Sem alterações', 'Nenhuma informação foi modificada.')->send();

            return;
        }

        $this->toast()->success('Evento atualizado', 'As informações do evento foram atualizadas.')->send();
    }

    public function render(): View
    {
        return view('livewire.events.edit.general', [
            'types' => EventTypeEnum::cases(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        $rules = [
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', Rule::enum(EventTypeEnum::class)],
            'is_public' => ['boolean'],
        ];

        if ($this->event->recurrence_code === null) {
            $rules['advertisable'] = ['boolean'];
        }

        return $rules;
    }

    private function loadEventValues(): void
    {
        $this->name         = $this->event->name;
        $this->type         = $this->event->type->value;
        $this->is_public    = $this->event->is_public;
        $this->advertisable = $this->event->advertisable;
    }
}
