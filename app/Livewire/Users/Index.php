<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class Index extends Component
{
    use WithPagination;

    public ?int $quantity = 5;

    public ?string $search = null;

    public array $sort = [
        'column'    => 'created_at',
        'direction' => 'desc',
    ];

    public array $headers = [
        ['index' => 'id', 'label' => '#'],
        ['index' => 'name', 'label' => 'Name'],
        ['index' => 'email', 'label' => 'E-mail'],
        ['index' => 'created_at', 'label' => 'Created'],
        ['index' => 'action', 'sortable' => false],
    ];

    public function render(): View
    {
        return view('livewire.users.index');
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', User::class);

        return User::query()
            ->whereNotIn('id', [Auth::id()])
            ->when(! Auth::user()->hasRole(UserRoleEnum::ADMIN->value), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('roles')
                ->orWhereJsonDoesntContain('roles', UserRoleEnum::ADMIN->value)))
            ->when($this->search !== null, fn (Builder $query) => $query->whereAny(['name', 'email'], 'like', '%'.mb_trim($this->search).'%'))
            ->orderBy(...array_values($this->sort))
            ->paginate($this->quantity)
            ->withQueryString();
    }
}
