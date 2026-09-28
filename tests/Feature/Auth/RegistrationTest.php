<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Community;

it('renders the register page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Criar conta');
});

it('registers a new user', function () {
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $this->post(route('register.store'), [
        'name'                  => 'New User',
        'communities'           => [$community->id],
        'email'                 => 'new@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('registration.pending'));

    $this->assertAuthenticated();

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeTrue();
});

it('requires a unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register.store'), [
        'name'                  => 'New User',
        'email'                 => 'taken@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
