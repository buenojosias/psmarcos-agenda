<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use Livewire\Attributes\Url;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use TallStackUi\Traits\Interactions;
use App\Actions\ResubmitRefusedEventAction;
use Illuminate\Validation\ValidationException;

class Edit extends Component
{
    use Interactions;

    public Event $event;

    #[Url]
    public string $tab = 'general';

    public bool $resubmitConfirmation = false;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        abort_if($event->status === EventStatusEnum::CANCELED, 403);

        $this->event = $event;

        if ($event->is_external && $this->tab === 'reservations') {
            $this->tab = 'general';
        }
    }

    public function openResubmitConfirmation(): void
    {
        Gate::authorize('update', $this->event);

        $this->event->refresh();

        if ($this->event->status !== EventStatusEnum::REFUSED) {
            return;
        }

        $this->resetErrorBag('resubmit');
        $this->resubmitConfirmation = true;
    }

    public function resubmit(ResubmitRefusedEventAction $resubmitRefusedEvent): void
    {
        try {
            $this->event = $resubmitRefusedEvent->handle($this->event);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            $this->addError('resubmit', is_string($message) ? $message : 'Não foi possível reenviar o evento para aprovação.');
            $this->resubmitConfirmation = false;

            return;
        }

        $this->resubmitConfirmation = false;

        $this->toast()
            ->success('Evento enviado', 'O evento foi enviado novamente para aprovação.')
            ->send();
    }

    public function render(): View
    {
        return view('livewire.events.edit', [
            'latestRefusalReason' => $this->event->status === EventStatusEnum::REFUSED
                ? $this->event->notes()->latest('id')->value('content')
                : null,
        ]);
    }
}
