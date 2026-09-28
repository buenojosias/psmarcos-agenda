<?php

declare(strict_types=1);

namespace App\Livewire\User\Profile;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Livewire\Traits\Alert;
use Illuminate\Contracts\View\View;

class Delete extends Component
{
    use Alert;

    public User $user;

    public ?string $password = null;

    public bool $modal = false;

    protected array $validationAttributes = [
        'password' => 'password',
    ];

    #[On('mount')]
    public function mount(): void
    {
        $this->user = user();
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function render(): View
    {
        return view('livewire.user.profile.delete');
    }

    public function confirm(): void
    {
        $this->validate();

        \Illuminate\Support\Facades\Gate::authorize('delete', $this->user);

        $this->modal = false;

        $this->question()
            ->confirm(method: 'delete')
            ->cancel()
            ->send();
    }

    public function delete(): void
    {
        $this->validate();

        \Illuminate\Support\Facades\Gate::authorize('delete', $this->user);
    }
}
