<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Models\Event;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class Attention extends Component
{
    public function render(): View
    {
        Gate::authorize('viewAny', Event::class);
        $user      = user();
        $decisions = [];

        if (Gate::allows('reviewAny', Event::class)) {
            $counts = Event::query()->visibleTo($user)->awaitingReview()
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

            foreach ([EventStatusEnum::RESCHEDULED, EventStatusEnum::PENDING] as $status) {
                $count = (int) ($counts[$status->value] ?? 0);

                if ($count > 0) {
                    $decisions[] = [
                        'title' => $status === EventStatusEnum::RESCHEDULED
                            ? trans_choice(':count evento alterado precisa de nova aprovação|:count eventos alterados precisam de nova aprovação', $count)
                            : trans_choice(':count evento aguarda aprovação|:count eventos aguardam aprovação', $count),
                        'url'    => route('events.index', ['status' => $status->value, 'period' => 'all']),
                        'action' => 'Revisar eventos',
                        'icon'   => $status === EventStatusEnum::RESCHEDULED ? 'arrow-path' : 'clipboard-document-check',
                        'color'  => $status->color(),
                    ];
                }
            }
        }

        if (Gate::allows('viewAny', User::class)) {
            $count = $this->pendingUsers($user);

            if ($count > 0) {
                $decisions[] = [
                    'title'  => trans_choice(':count cadastro aguarda aprovação|:count cadastros aguardam aprovação', $count),
                    'url'    => route('users.index', ['status' => 'pending']),
                    'action' => 'Aprovar usuários',
                    'icon'   => 'user-plus',
                    'color'  => 'yellow',
                ];
            }
        }

        $canAdjustCreatedEvents = Gate::allows('update', new Event(['created_by_user_id' => $user->id]));
        $refusedEvents          = Event::query()->visibleTo($user)->where('status', EventStatusEnum::REFUSED)
            ->where(fn (Builder $query): Builder => $query->fromUserGroups($user)
                ->when($canAdjustCreatedEvents, fn (Builder $query): Builder => $query->orWhere('created_by_user_id', $user->id)))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(3)->get(['id', 'name']);

        return view('livewire.dashboard.attention', compact('decisions', 'refusedEvents'));
    }

    private function pendingUsers(User $user): int
    {
        $query = User::query()->where('is_active', false)->whereNull('approved_at')->whereNull('deactivated_at')
            ->whereKeyNot($user->id);

        foreach (UserRoleEnum::cases() as $role) {
            if (! in_array($role, UserRoleEnum::assignableBy($user), true)) {
                $query->where(fn (Builder $query): Builder => $query->whereNull('roles')->orWhereJsonDoesntContain('roles', $role->value));
            }
        }

        return $query->count();
    }
}
