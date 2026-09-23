<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\User;
use App\Models\Event;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Notes extends Component
{
    public Event $event;

    public string $content = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->authorizeAccess();
    }

    public function save(): void
    {
        $user          = $this->authorizeAccess();
        $this->content = mb_trim($this->content);
        $validated     = $this->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $this->event->notes()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        $this->reset('content');
    }

    public function render(): View
    {
        $this->authorizeAccess();

        return view('livewire.events.notes', [
            'notes' => $this->event->notes()
                ->with('user:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(),
        ]);
    }

    private function authorizeAccess(): User
    {
        $user = user();
        abort_if($user === null, 401);
        Gate::authorize('view', $this->event);
        abort_unless($user->is_active && (Gate::allows('update', $this->event)
            || $user->hasRole(UserRoleEnum::PRIEST->value)), 403);

        return $user;
    }
}
