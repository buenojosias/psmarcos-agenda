<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Component;
use App\Models\EventLog;
use App\Support\EventLogFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Audit extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        Gate::authorize('audit', $event);

        $this->event = $event;
    }

    public function render(): View
    {
        Gate::authorize('audit', $this->event);

        $logs = $this->event->logs()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        /** @var list<list<EventLog>> $logGroups */
        $logGroups = [];

        foreach ($logs as $log) {
            $lastGroupIndex = count($logGroups) - 1;

            if ($log->operation_code !== null
                && $lastGroupIndex >= 0
                && $logGroups[$lastGroupIndex][0]->operation_code === $log->operation_code) {
                $logGroups[$lastGroupIndex][] = $log;

                continue;
            }

            $logGroups[] = [$log];
        }

        return view('livewire.events.audit', [
            'logGroups'        => $logGroups,
            'formattedChanges' => app(EventLogFormatter::class)->formatMany($logs),
        ]);
    }
}
