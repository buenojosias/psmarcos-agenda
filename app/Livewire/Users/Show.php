<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use App\Enums\UserRoleEnum;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\Users\ApproveUserAction;
use App\Actions\Users\SetUserActiveAction;

class Show extends Component
{
    use Alert;

    public User $user;

    public array $roles = ['member'];

    public function mount(): void
    {
        Gate::authorize('view', $this->user);
    }

    #[Computed]
    public function details(): User
    {
        $user = $this->user->fresh(['communities', 'approvedBy']);
        Gate::authorize('view', $user);

        return $user;
    }

    #[Computed]
    public function roleOptions(): array
    {
        return UserRoleEnum::optionsFor(auth()->user());
    }

    public function approve(ApproveUserAction $approve): void
    {
        $this->user = $approve->handle($this->user, auth()->user(), $this->roles);
        unset($this->details);
        $this->success('Cadastro aprovado. O acesso à agenda está liberado.', 'Concluído');
    }

    public function setActive(bool $active, SetUserActiveAction $setActive): void
    {
        $this->user = $setActive->handle($this->user, auth()->user(), $active);
        unset($this->details);
        $this->success($active ? 'Conta reativada.' : 'Conta desativada.', 'Concluído');

        if (! $active && $this->user->id === auth()->id()) {
            $this->redirectRoute('registration.pending');
        }
    }

    public function render(): View
    {
        return view('livewire.users.show');
    }
}
