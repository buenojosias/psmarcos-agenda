<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class Index extends Component
{
    use WithPagination;

    public ?int $quantity = 10;

    public ?string $search = null;

    public array $sort = [
        'column'    => 'name',
        'direction' => 'asc',
    ];

    public array $headers = [
        ['index' => 'name', 'label' => 'Nome'],
        ['index' => 'type', 'label' => 'Tipo', 'sortable' => false],
        ['index' => 'community', 'label' => 'Comunidade', 'sortable' => false],
    ];

    public function render(): View
    {
        return view('livewire.groups.index');
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortColumn = in_array($this->sort['column'] ?? null, ['name', 'id'], true)
            ? $this->sort['column']
            : 'name';
        $sortDirection = ($this->sort['direction'] ?? null) === 'desc' ? 'desc' : 'asc';

        return Group::query()
            ->with('community:id,name')
            ->when(filled($this->search), fn (Builder $query) => $query->where('name', 'like', '%'.mb_trim($this->search).'%'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($this->quantity)
            ->withQueryString();
    }
}
