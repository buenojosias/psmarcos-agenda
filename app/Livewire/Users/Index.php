<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use Livewire\Attributes\Url;
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

    public ?int $quantity = 10;

    #[Url]
    public ?string $search = null;

    #[Url]
    public string $status = '';

    #[Url]
    public ?int $community = null;

    #[Url]
    public ?string $role = null;

    public array $sort = [
        'column'    => 'created_at',
        'direction' => 'desc',
    ];

    public array $headers = [
        ['index' => 'name', 'label' => 'Nome'],
        ['index' => 'email', 'label' => 'E-mail'],
        ['index' => 'communities', 'label' => 'Comunidades', 'sortable' => false],
        ['index' => 'roles', 'label' => 'Perfis', 'sortable' => false],
        ['index' => 'status', 'label' => 'Status', 'sortable' => false],
        ['index' => 'created_at', 'label' => 'Cadastro'],
        ['index' => 'action', 'sortable' => false],
    ];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'community', 'role', 'quantity'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function communityOptions(): array
    {
        return Community::query()->orderBy('name')->get(['id', 'name'])->map(fn (Community $community): array => ['label' => $community->name, 'value' => $community->id])->all();
    }

    #[Computed]
    public function roleOptions(): array
    {
        return UserRoleEnum::optionsFor(auth()->user());
    }

    public function render(): View
    {
        return view('livewire.users.index');
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', User::class);

        $column    = in_array($this->sort['column'] ?? '', ['id', 'name', 'email', 'created_at'], true) ? $this->sort['column'] : 'created_at';
        $direction = ($this->sort['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return User::query()->with('communities')
            ->whereNotIn('id', [Auth::id()])
            ->when(! Auth::user()->hasRole(UserRoleEnum::ADMIN->value), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('roles')
                ->orWhereJsonDoesntContain('roles', UserRoleEnum::ADMIN->value)))
            ->when($this->search !== null, fn (Builder $query) => $query->whereAny(['name', 'email'], 'like', '%'.mb_trim($this->search).'%'))
            ->when($this->status === 'pending', fn (Builder $query) => $query->where('is_active', false)->whereNull('approved_at')->whereNull('deactivated_at'))
            ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false)->where(fn (Builder $status) => $status->whereNotNull('approved_at')->orWhereNotNull('deactivated_at')))
            ->when($this->community, fn (Builder $query) => $query->whereHas('communities', fn (Builder $communities) => $communities->whereKey($this->community)))
            ->when($this->role, fn (Builder $query) => $query->whereJsonContains('roles', $this->role))
            ->orderBy($column, $direction)->orderBy('id', $direction)
            ->paginate(in_array($this->quantity, [2, 5, 15, 25], true) ? $this->quantity : 10)
            ->withQueryString();
    }
}
