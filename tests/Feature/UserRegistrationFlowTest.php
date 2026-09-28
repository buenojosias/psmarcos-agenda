<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->withoutVite();
});

it('creates an authenticated pending member with all selected communities through Livewire', function () {
    $first  = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $second = Community::create(['name' => 'São João', 'abbreviation' => 'SJ', 'alias' => 'sao-joao']);

    Livewire::test(Register::class)->set([
        'name'        => 'Maria Silva', 'email' => 'MARIA@example.com',
        'password'    => 'password123', 'password_confirmation' => 'password123',
        'communities' => [$first->id, $second->id],
    ])->call('save')->assertHasNoErrors()->assertRedirect(route('registration.pending'));

    $user = User::where('email', 'maria@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->is_active)->toBeFalse();
    expect($user->roles)->toBe(['member']);
    expect($user->approved_at)->toBeNull();
    expect($user->approved_by_user_id)->toBeNull();
    expect($user->communities()->pluck('communities.id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
    expect(Hash::check('password123', $user->password))->toBeTrue();
    $this->get(route('dashboard'))->assertRedirect(route('registration.pending'));
    $this->get(route('registration.pending'))->assertOk()->assertSee('Cadastro recebido')->assertDontSee('Mapa de disponibilidade');
});

it('requires existing distinct communities before creating a public account', function (array $communities, string $error) {
    Livewire::test(Register::class)->set([
        'name'        => 'Maria Silva', 'email' => 'maria@example.com',
        'password'    => 'password123', 'password_confirmation' => 'password123',
        'communities' => $communities,
    ])->call('save')->assertHasErrors($error);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
})->with([
    'empty selection'   => [[], 'communities'],
    'unknown community' => [[999], 'communities.0'],
    'duplicated IDs'    => [[999, 999], 'communities.0'],
]);

it('ignores forged roles active state and approval data on the Fortify endpoint', function () {
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);

    $this->post(route('register.store'), [
        'name'        => 'Maria', 'email' => 'maria@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        'communities' => [$community->id], 'roles' => ['admin'], 'is_active' => true, 'approved_at' => now(), 'approved_by_user_id' => 1,
    ])->assertRedirect(route('registration.pending'));

    $user = User::where('email', 'maria@example.com')->firstOrFail();
    expect($user->roles)->toBe(['member']);
    expect($user->is_active)->toBeFalse();
    expect($user->approved_at)->toBeNull();
});

it('allows inactive users to log in but ignores an intended protected destination', function () {
    $user = User::factory()->pending()->create();

    $this->withSession(['url.intended' => route('users.index')])->post(route('login.store'), ['email' => $user->email, 'password' => 'Test123!'])
        ->assertRedirect(route('registration.pending'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertRedirect(route('registration.pending'));
    $this->get(route('user.profile'))->assertRedirect(route('registration.pending'));
    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('redirects active users from pending to the dashboard', function () {
    $this->actingAs(User::factory()->active()->create())->get(route('registration.pending'))->assertRedirect(route('dashboard'));
});

it('redirects guests from the pending page to login', function () {
    $this->get(route('registration.pending'))->assertRedirect(route('login'));
});

it('blocks authenticated inactive Livewire requests and Fortify profile mutations', function () {
    $user = User::factory()->pending()->create();
    $this->actingAs($user)->post(Livewire::getUpdateUri(), [])->assertRedirect(route('registration.pending'));
    $this->post(route('two-factor.enable'))->assertRedirect(route('registration.pending'));
    expect($user->fresh()->name)->toBe($user->name);
});

it('routes inactive two factor users to pending after a valid recovery code', function () {
    $user = User::factory()->pending()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Test123!'])->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['recovery_code' => $user->recoveryCodes()[0]])->assertRedirect(route('registration.pending'));
    $this->assertAuthenticatedAs($user);
});
