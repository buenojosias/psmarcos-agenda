<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use App\Models\PlaceReservation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Show extends Component
{
    public Event $event;

    public bool $showNotes = false;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function openNotes(): void
    {
        Gate::authorize('view', $this->event);
        abort_unless($this->canAccessNotes(), 403);

        $this->showNotes = true;
    }

    public function render(): View
    {
        Gate::authorize('view', $this->event);
        $this->event->load(['group', 'detail', 'community']);

        $reservations = $this->event->is_external
            ? null
            : $this->event->reservations()->with('place.main:id,name')
                ->orderByDesc('is_primary')->orderBy('reserved_from')->orderBy('id')->get()
                ->each(function (PlaceReservation $reservation): void {
                    $reservation->setAttribute('highlight', $reservation->is_primary ? 'primary' : null);
                });

        $canUpdate = Gate::allows('update', $this->event);

        return view('livewire.events.show', [
            'canManage'          => Gate::allows('manage', $this->event),
            'canUpdate'          => $canUpdate,
            'canViewNotes'       => $this->canAccessNotes($canUpdate),
            'canReview'          => Gate::allows('review', $this->event),
            'primaryReservation' => $reservations?->firstWhere('is_primary', true),
            'reservations'       => $reservations,
        ]);
    }

    private function canAccessNotes(?bool $canUpdate = null): bool
    {
        $user = user();

        return $user !== null
            && $user->is_active
            && (($canUpdate ?? Gate::allows('update', $this->event)) || $user->hasRole(UserRoleEnum::PRIEST->value));
    }
}
