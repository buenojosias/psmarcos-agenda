<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Component;
use App\Models\Community;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public array $communities = [];

    #[Computed]
    public function communityOptions(): array
    {
        return Community::query()->orderBy('name')->get(['id', 'name'])->map(fn (Community $community): array => ['label' => $community->name, 'value' => $community->id])->all();
    }

    public function save(CreateNewUser $register): void
    {
        abort_if(Auth::check(), 403);
        $key = 'registration:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde um minuto e tente novamente.']);
        }
        RateLimiter::hit($key, 60);
        $user = $register->create([
            'name'                  => $this->name, 'email' => $this->email, 'password' => $this->password,
            'password_confirmation' => $this->password_confirmation, 'communities' => $this->communities,
        ]);
        event(new Registered($user));
        Auth::login($user);
        session()->regenerate();
        $this->redirectRoute('registration.pending');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
