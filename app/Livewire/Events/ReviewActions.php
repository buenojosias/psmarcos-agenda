<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use App\Actions\RefuseEventAction;
use App\Actions\ApproveEventAction;
use Illuminate\Contracts\View\View;
use TallStackUi\Traits\Interactions;

class ReviewActions extends Component
{
    use Interactions;

    public Event $event;

    public bool $showRefuseModal = false;

    public string $refusalReason = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function approve(ApproveEventAction $approveEvent): void
    {
        $user = user();
        abort_if($user === null, 401);

        $this->event = $approveEvent->handle($this->event, $user);

        $this->toast()
            ->success('Evento aprovado', 'O evento foi aprovado com sucesso.')
            ->send();

        $this->dispatch('event-reviewed');
    }

    public function openRefuseModal(): void
    {
        $this->resetValidation();
        $this->showRefuseModal = true;
    }

    public function refuse(RefuseEventAction $refuseEvent): void
    {
        $validated = $this->validate([
            'refusalReason' => ['required', 'string', 'max:2000'],
        ]);

        $user = user();
        abort_if($user === null, 401);

        $this->event           = $refuseEvent->handle($this->event, $user, $validated['refusalReason']);
        $this->showRefuseModal = false;
        $this->reset('refusalReason');

        $this->toast()
            ->success('Evento recusado', 'O evento foi recusado com sucesso.')
            ->send();

        $this->dispatch('event-reviewed');
    }

    public function render(): View
    {
        return view('livewire.events.review-actions');
    }
}
