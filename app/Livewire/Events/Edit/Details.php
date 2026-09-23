<?php

declare(strict_types=1);

namespace App\Livewire\Events\Edit;

use App\Models\Event;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use TallStackUi\Traits\Interactions;
use App\Actions\UpdateEventDetailsAction;

class Details extends Component
{
    use Interactions;

    public Event $event;

    public ?string $description = null;

    public ?string $target_audience = null;

    public ?string $participation_instructions = null;

    public bool $registration_required = false;

    public ?string $registration_url = null;

    public ?string $registration_deadline = null;

    public ?string $participation_cost = null;

    public ?string $contact_name = null;

    public ?string $contact_phone = null;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);

        $this->event = $event;
        $this->loadDetails();
    }

    public function save(UpdateEventDetailsAction $updateEventDetails): void
    {
        $user = user();
        abort_if($user === null, 401);

        $changed = $updateEventDetails->handle($this->event, $this->validate(), $user);

        $this->event->refresh();
        $this->loadDetails();

        if (! $changed) {
            $this->toast()->info('Sem alterações', 'Nenhum detalhe foi modificado.')->send();

            return;
        }

        $this->toast()->success('Detalhes atualizados', 'As informações do evento foram salvas.')->send();
    }

    public function render(): View
    {
        return view('livewire.events.edit.details');
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
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

    private function loadDetails(): void
    {
        $detail = $this->event->detail()->first();

        $this->description                = $detail?->description;
        $this->target_audience            = $detail?->target_audience;
        $this->participation_instructions = $detail?->participation_instructions;
        $this->registration_required      = $detail->registration_required ?? false;
        $this->registration_url           = $detail?->registration_url;
        $this->registration_deadline      = $detail?->registration_deadline?->format('Y-m-d');
        $this->participation_cost         = $detail?->participation_cost;
        $this->contact_name               = $detail?->contact_name;
        $this->contact_phone              = $detail?->contact_phone;
    }
}
