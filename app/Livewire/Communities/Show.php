<?php

declare(strict_types=1);

namespace App\Livewire\Communities;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\Url;
use App\Livewire\Traits\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Show extends Component
{
    use Alert;

    public Community $community;

    #[Url(except: 'information')]
    public string $tab = 'information';

    public function mount(Community $community): void
    {
        Gate::authorize('view', $community);

        $this->community = $community;
        $this->normalizeTab();
    }

    public function updatedTab(): void
    {
        $this->normalizeTab();
    }

    public function refreshCommunity(): void
    {
        Gate::authorize('view', $this->community);

        $this->community->refresh();
    }

    public function delete(): void
    {
        Gate::authorize('delete', $this->community);

        $this->question('Deseja excluir a comunidade '.$this->community->alias.'?', 'Excluir comunidade')
            ->confirm('Excluir', 'confirmDelete')
            ->cancel('Cancelar')
            ->send();
    }

    public function confirmDelete(): void
    {
        try {
            $deleted = DB::transaction(function (): bool {
                $community = Community::query()->lockForUpdate()->findOrFail($this->community->id);
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
        $this->redirectRoute('communities.index');
    }

    public function render(): View
    {
        Gate::authorize('view', $this->community);

        return view('livewire.communities.show', [
            'groups' => $this->tab === 'groups'
                ? $this->community->groups()->orderBy('name')->get(['id', 'name', 'abbreviation', 'type'])
                : null,
        ]);
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, ['information', 'groups', 'spaces', 'calendar'], true)) {
            $this->tab = 'information';
        }
    }
}
