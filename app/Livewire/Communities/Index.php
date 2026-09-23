<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Index extends Component
{
    use Alert;

    public array $sort = [
        'column'    => 'name',
        'direction' => 'asc',
    ];

    public array $headers = [
        ['index' => 'name', 'label' => 'Nome'],
        ['index' => 'address', 'label' => 'Endereço', 'sortable' => false],
        ['index' => 'action', 'label' => 'Ações', 'sortable' => false],
    ];

    public function render(): View
    {
        Gate::authorize('viewAny', Community::class);

        return view('livewire.communities.index');
    }

    #[Computed]
    public function rows()
    {
        Gate::authorize('viewAny', Community::class);

        $sortColumn = in_array($this->sort['column'] ?? null, ['alias', 'name', 'abbreviation'], true)
            ? $this->sort['column']
            : 'alias';
        $sortDirection = ($this->sort['direction'] ?? null) === 'desc' ? 'desc' : 'asc';

        return Community::query()
            ->orderBy($sortColumn, $sortDirection)
            ->get();
    }

    public function delete(int $id): void
    {
        $community = Community::findOrFail($id);
        Gate::authorize('delete', $community);

        $this->question('Deseja excluir a comunidade '.$community->alias.'?', 'Excluir comunidade')
            ->confirm('Excluir', 'confirmDelete', $community->id)
            ->cancel('Cancelar')
            ->send();
    }

    public function confirmDelete(int $id): void
    {
        try {
            $deleted = DB::transaction(function () use ($id): bool {
                $community = Community::query()->lockForUpdate()->findOrFail($id);
                Gate::authorize('delete', $community);

                if ($community->places()->exists()
                    || $community->groups()->withoutGlobalScope(SoftDeletingScope::class)->exists()
                    || $community->users()->exists()) {
                    return false;
                }

                return $community->delete();
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            $deleted = false;
        }

        if (! $deleted) {
            $this->error('Não é possível excluir esta comunidade porque existem registros vinculados a ela.');

            return;
        }

        $this->success('Comunidade excluída com sucesso.', 'Concluído');
    }
}
