<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Users\ApproveUserAction;

class Create extends Component
{
    use Alert;

    public string $name = '';

    public string $email = '';

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public array $roles = ['member'];

    public array $communities = [];

    public bool $modal = false;

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

    public function save(CreateNewUser $register, ApproveUserAction $approve): void
    {
        Gate::authorize('create', User::class);
        $this->validate([
            'roles'   => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(UserRoleEnum::assignableValues(auth()->user()))],
        ]);
        DB::transaction(function () use ($register, $approve): void {
            $user = $register->create([
                'name'                  => $this->name, 'email' => $this->email, 'password' => $this->password,
                'password_confirmation' => $this->password_confirmation, 'communities' => $this->communities,
            ]);
            $approve->handle($user, auth()->user(), $this->roles);
        });
        $this->reset();
        $this->dispatch('created');
        $this->success('Usuário criado e aprovado com sucesso.', 'Concluído');
    }

    public function render(): View
    {
        return view('livewire.users.create');
    }
}
