<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use Livewire\Attributes\On;
use App\Livewire\Traits\Alert;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\Users\UpdateUserAction;

class Update extends Component
{
    use Alert;

    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public array $roles = ['member'];

    public array $communities = [];

    public bool $modal = false;

    #[Locked]
    public bool $page = false;

    public function mount(?User $user = null, bool $page = false): void
    {
        $this->page = $page || request()->routeIs('users.edit');

        if ($user?->exists) {
            $this->fillUser($user);
        }
    }

    #[On('load::user')]
    public function load(User $user): void
    {
        Gate::authorize('update', $user);
        $this->resetValidation();
        $this->fillUser($user);
        $this->modal = true;
    }

    #[Computed]
    public function roleOptions(): array
    {
        return UserRoleEnum::optionsFor(auth()->user());
    }

    #[Computed]
    public function communityOptions(): array
    {
        return Community::query()->orderBy('name')->get(['id', 'name'])->map(fn (Community $community): array => ['label' => $community->name, 'value' => $community->id])->all();
    }

    public function save(UpdateUserAction $update): void
    {
        Gate::authorize('update', $this->user);
        $update->handle($this->user, auth()->user(), [
            'name'     => $this->name, 'email' => $this->email,
            'password' => $this->password, 'password_confirmation' => $this->password_confirmation,
            'roles'    => $this->roles, 'communities' => $this->communities,
        ]);
        $this->password              = null;
        $this->password_confirmation = null;
        $this->modal                 = false;
        $this->dispatch('updated');
        $this->success('Usuário atualizado com sucesso.', 'Concluído');
    }

    public function render(): View
    {
        return view('livewire.users.update');
    }

    private function fillUser(User $user): void
    {
        $this->user                  = $user;
        $this->name                  = $user->name;
        $this->email                 = $user->email;
        $this->roles                 = $user->getRolesArray();
        $this->communities           = $user->communities()->pluck('communities.id')->all();
        $this->password              = null;
        $this->password_confirmation = null;
    }
}
