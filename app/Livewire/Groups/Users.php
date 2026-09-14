<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class Users extends Component
{
    public Group $group;

    public bool $modal = false;

    public ?int $userId = null;

    public bool $isLeader = false;

    public function addUser(): void
    {
        Gate::authorize('manageUsers', $this->group);

        $this->validate([
            'userId'   => ['required', 'integer', Rule::exists('users', 'id')],
            'isLeader' => ['boolean'],
        ]);

        if ($this->group->users()->whereKey($this->userId)->exists()) {
            $this->addError('userId', 'Este usuário já está vinculado ao grupo.');

            return;
        }

        $this->group->users()->attach($this->userId, ['is_coordinator' => $this->isLeader]);
        $this->reset('userId', 'isLeader', 'modal');
    }

    public function removeUser(int $userId): void
    {
        Gate::authorize('manageUsers', $this->group);

        $this->group->users()->detach($userId);
    }

    public function render(): View
    {
        return view('livewire.groups.users', [
            'canManageUsers' => Gate::allows('manageUsers', $this->group),
            'users'          => $this->group->users()->orderBy('name')->get(),
        ]);
    }
}
