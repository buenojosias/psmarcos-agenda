<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Livewire\Users\Create;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    User::query()->delete();
    $this->community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $this->actingAs(User::factory()->create(['roles' => ['admin'], 'is_active' => true]));
});

it('renders the create user component', function () {
    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->assertOk()
        ->assertViewIs('livewire.users.create');
});

it('initializes with a new user', function () {
    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->assertSet('name', '')
        ->assertSet('password', null)
        ->assertSet('password_confirmation', null);
});

it('validates user creation with valid data', function () {
    $data = [
        'name'                  => 'John Doe',
        'email'                 => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set($data)
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('users', [
        'name'  => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

it('requires name', function () {
    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set('name', '')
        ->set('email', 'john@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('requires unique email', function () {
    User::create([
        'name'     => 'Existing User',
        'email'    => 'existing@example.com',
        'password' => bcrypt('password123'),
    ]);

    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set('name', 'John Doe')
        ->set('email', 'existing@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

it('validates email format', function () {
    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set('name', 'John Doe')
        ->set('email', 'invalid-email')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

it('requires password confirmation', function () {
    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'different-password')
        ->call('save')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('requires minimum password length', function () {
    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('save')
        ->assertHasErrors('password');
});

it('does not claim email verification when creating user', function () {
    $data = [
        'name'                  => 'John Doe',
        'email'                 => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set($data)
        ->call('save');

    $user = User::where('email', 'john@example.com')->first();

    expect($user->email_verified_at)->toBeNull();
});

it('resets form after successful creation', function () {
    $data = [
        'name'                  => 'John Doe',
        'email'                 => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set($data)
        ->call('save')
        ->assertSet('name', '')
        ->assertSet('password', null)
        ->assertSet('password_confirmation', null);
});

it('dispatches created event', function () {
    $data = [
        'name'                  => 'John Doe',
        'email'                 => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)->set('communities', [$this->community->id])
        ->set($data)
        ->call('save')
        ->assertDispatched('created');
});
