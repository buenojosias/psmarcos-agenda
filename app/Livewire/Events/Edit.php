<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use Livewire\Attributes\Url;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Edit extends Component
{
    public Event $event;

    #[Url]
    public string $tab = 'general';

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        abort_if($event->status === EventStatusEnum::CANCELED, 403);

        $this->event = $event;

        if ($event->is_external && $this->tab === 'reservations') {
            $this->tab = 'general';
        }
    }

    public function render(): View
    {
        return view('livewire.events.edit');
    }
}
