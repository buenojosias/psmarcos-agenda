<?php

declare(strict_types=1);

namespace App\Livewire\Places;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\On;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Delete extends Component
{
    use Alert;

    public Community $community;

    #[Locked]
    public ?int $placeId = null;

    #[On('delete-place')]
    public function confirm(int $placeId): void
    {
        Gate::authorize('view', $this->community);

        $place = $this->community->places()->findOrFail($placeId);
        Gate::authorize('delete', $place);

        $this->reset('placeId');

        if ($place->reservations()->exists()) {
            $this->showReservationWarning();

            return;
        }

        $this->placeId = $place->id;

        $this->question('Deseja excluir este espaço? Esta ação não pode ser desfeita.', 'Excluir espaço')
            ->confirm('Confirmar', 'delete')
            ->cancel('Cancelar')
            ->send();
    }

    public function delete(): void
    {
        Gate::authorize('view', $this->community);

        $deleted = DB::transaction(function (): bool {
            $place = $this->community->places()->lockForUpdate()->findOrFail($this->placeId);
            Gate::authorize('delete', $place);

            if ($place->reservations()->exists()) {
                return false;
            }

            $place->delete();

            return true;
        });

        $this->reset('placeId');

        if (! $deleted) {
            $this->showReservationWarning();

            return;
        }

        $this->dispatch('deleted');
        $this->success('Espaço excluído com sucesso.', 'Concluído');
    }

    public function render(): View
    {
        return view('livewire.places.delete');
    }

    private function showReservationWarning(): void
    {
        $this->dispatch('ts-ui:dialog',
            reference: $this->getId(),
            type: 'warning',
            title: 'Não é possível excluir este espaço',
            description: 'Este espaço possui reservas vinculadas. Remova ou transfira essas reservas antes de tentar excluí-lo novamente.',
            options: [
                'cancel' => [
                    'text'   => 'Fechar',
                    'static' => true,
                    'method' => null,
                    'params' => null,
                ],
            ],
        );
    }
}
