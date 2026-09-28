<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Event;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Index extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $user = user();
        $time = now();

        return view('livewire.dashboard.index', [
            'firstName' => explode(' ', mb_trim($user->name))[0],
            'greeting'  => match (true) {
                $time->hour < 12 => 'Bom dia',
                $time->hour < 18 => 'Boa tarde',
                default          => 'Boa noite',
            },
            'date'           => $time->locale('pt_BR')->translatedFormat('l, d \d\e F'),
            'canCreateEvent' => Gate::allows('create', Event::class),
            'isPascom'       => $user->hasRole(UserRoleEnum::PASCOM->value),
            'isPriest'       => $user->hasRole(UserRoleEnum::PRIEST->value),
        ]);
    }
}
