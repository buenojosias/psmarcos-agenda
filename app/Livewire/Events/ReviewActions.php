<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use App\Actions\RejectEventAction;
use App\Actions\ApproveEventAction;
use Illuminate\Contracts\View\View;
use TallStackUi\Traits\Interactions;

class ReviewActions extends Component
{
    use Interactions;

    public Event $event;

    public bool $showRejectModal = false;

    public string $rejectionReason = '';

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

    public function openRejectModal(): void
    {
        $this->resetValidation();
        $this->showRejectModal = true;
    }

    public function reject(RejectEventAction $rejectEvent): void
    {
        $validated = $this->validate([
            'rejectionReason' => ['required', 'string', 'max:2000'],
        ]);

        $user = user();
        abort_if($user === null, 401);

        $this->event           = $rejectEvent->handle($this->event, $user, $validated['rejectionReason']);
        $this->showRejectModal = false;
        $this->reset('rejectionReason');

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
