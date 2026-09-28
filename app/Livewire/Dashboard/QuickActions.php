<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use Livewire\Component;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class QuickActions extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $actions = [];

        if (Gate::allows('reviewAny', Event::class)) {
            $actions[] = $this->action('Revisar eventos', 'clipboard-document-check', 'events.index', ['status' => 'pending', 'period' => 'all']);
        }

        if (Gate::allows('viewAny', User::class)) {
            $actions[] = $this->action('Usuários', 'users', 'users.index');
        }

        if (Gate::allows('create', Event::class)) {
            $actions[] = $this->action('Novo evento', 'plus', 'events.create');
        }
        $actions[] = $this->action('Agenda', 'calendar-days', 'events.index');

        if (Gate::allows('viewAny', Community::class)) {
            $actions[] = $this->action('Disponibilidade', 'table-cells', 'availability.index');
        }

        if (user()->hasRole(UserRoleEnum::PRIEST->value) && Gate::allows('create', Mass::class)) {
            $actions[] = $this->action('Missas', 'building-library', 'masses.index');
        } else {
            $actions[] = $this->action('Grupos', 'user-group', 'groups.index');
        }

        if (user()->hasAnyRole([UserRoleEnum::PASCOM->value, UserRoleEnum::ADMIN->value]) && Gate::allows('viewAny', Community::class)) {
            $actions[] = $this->action('Comunidades', 'building-library', 'communities.index');
        }

        return view('livewire.dashboard.quick-actions', compact('actions'));
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{label: string, icon: string, url: string}
     */
    private function action(string $label, string $icon, string $route, array $parameters = []): array
    {
        return ['label' => $label, 'icon' => $icon, 'url' => route($route, $parameters)];
    }
}
