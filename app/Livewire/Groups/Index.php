<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;
use App\Models\Community;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class Index extends Component
{
    use WithPagination;

    public ?string $search = null;

    public mixed $community = '';

    public bool $myGroups = false;

    public array $sort = [
        'column'    => 'name',
        'direction' => 'asc',
    ];

    public array $headers = [
        ['index' => 'name', 'label' => 'Nome'],
        ['index' => 'type', 'label' => 'Tipo', 'sortable' => false],
        ['index' => 'community', 'label' => 'Comunidade', 'sortable' => false],
        ['index' => 'actions', 'label' => '', 'sortable' => false],
    ];

    public function render(): View
    {
        return view('livewire.groups.index', [
            'communities' => Community::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'community', 'myGroups'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortColumn = in_array($this->sort['column'] ?? null, ['name', 'id'], true)
            ? $this->sort['column']
            : 'name';
        $sortDirection = ($this->sort['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $communityId   = filter_var($this->community, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return Group::query()
            ->with('community:id,name')
            ->when(filled($this->search), fn (Builder $query) => $query->where('name', 'like', '%'.mb_trim($this->search).'%'))
            ->when($communityId !== false, fn (Builder $query) => $query->where('community_id', $communityId))
            ->when($this->myGroups, fn (Builder $query) => $query->whereHas('users', fn (Builder $users) => $users->whereKey(auth()->id())))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(10)
            ->withQueryString();
    }
}
