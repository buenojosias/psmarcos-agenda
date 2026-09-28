<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Users\Show;
use App\Livewire\Users\Index;
use App\Livewire\Users\Create;
use App\Livewire\Users\Update;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Actions\Users\ApproveUserAction;
use App\Actions\Users\SetUserActiveAction;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->withoutVite();
});

it('approves pending accounts and records the responsible user for each allowed role', function (string $role) {
    $this->freezeSecond();
    $actor     = User::factory()->active()->create(['roles' => [$role]]);
    $target    = User::factory()->pending()->create();
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $target->communities()->attach($community);

    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->assertSet('roles', ['member'])
        ->assertSee('São Marcos')->call('approve')->assertHasNoErrors();

    $target->refresh();
    expect($target->is_active)->toBeTrue();
    expect($target->roles)->toBe(['member']);
    expect($target->approved_by_user_id)->toBe($actor->id);
    expect($target->approved_at->equalTo(now()))->toBeTrue();
    expect($actor->approvedUsers()->first()->id)->toBe($target->id);
    $this->actingAs($target)->get(route('dashboard'))->assertOk();
})->with(['secretary', 'cpp', 'admin']);

it('forbids approval by members and inactive managers in the domain action', function (string $role, bool $active) {
    $actor  = User::factory()->create(['roles' => [$role], 'is_active' => $active]);
    $target = User::factory()->pending()->create();

    expect(fn () => app(ApproveUserAction::class)->handle($target, $actor, ['member']))->toThrow(AuthorizationException::class);
    expect($target->fresh()->is_active)->toBeFalse();
})->with([['member', true], ['secretary', false], ['cpp', false], ['admin', false]]);

it('requires a role before approval and leaves the account pending on failure', function () {
    $actor  = User::factory()->create(['roles' => ['secretary']]);
    $target = User::factory()->pending()->create();

    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->set('roles', [])->call('approve')->assertHasErrors('roles');
    expect($target->fresh()->isPending())->toBeTrue();
});

it('rejects forbidden roles in approval editing and administrative creation', function (string $actorRole, string $forbiddenRole) {
    $actor     = User::factory()->active()->create(['roles' => [$actorRole]]);
    $pending   = User::factory()->pending()->create();
    $existing  = User::factory()->create();
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $existing->communities()->attach($community);

    expect(UserRoleEnum::assignableValues($actor))->not->toContain($forbiddenRole);
    Livewire::actingAs($actor)->test(Show::class, ['user' => $pending])->set('roles', [$forbiddenRole])->call('approve')->assertHasErrors('roles.0');
    Livewire::test(Update::class, ['user' => $existing])->set('roles', [$forbiddenRole])->call('save')->assertHasErrors('roles.0');
    Livewire::test(Create::class)->set([
        'name'        => 'Forged User', 'email' => 'forged@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        'communities' => [$community->id], 'roles' => [$forbiddenRole],
    ])->call('save')->assertHasErrors('roles.0');

    expect($pending->fresh()->isPending())->toBeTrue();
    expect($existing->fresh()->roles)->toBe(['member']);
    $this->assertDatabaseMissing('users', ['email' => 'forged@example.com']);
})->with([
    'secretary assigning CPP'   => ['secretary', 'cpp'],
    'secretary assigning admin' => ['secretary', 'admin'],
    'CPP assigning admin'       => ['cpp', 'admin'],
    'invented role'             => ['admin', 'super-admin'],
]);

it('allows permitted elevated roles in both approval and editing', function (string $actorRole, string $assignedRole) {
    $actor     = User::factory()->create(['roles' => [$actorRole]]);
    $target    = User::factory()->pending()->create();
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $target->communities()->attach($community);

    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->set('roles', [$assignedRole])->call('approve')->assertHasNoErrors();
    Livewire::test(Update::class, ['user' => $target])->set('roles', ['member', $assignedRole])->call('save')->assertHasNoErrors();
    expect($target->fresh()->roles)->toBe(['member', $assignedRole]);
})->with([['cpp', 'cpp'], ['admin', 'cpp'], ['admin', 'admin'], ['secretary', 'priest'], ['secretary', 'pascom']]);

it('prevents a secretary from editing or deactivating an existing CPP account', function () {
    $actor  = User::factory()->create(['roles' => ['secretary']]);
    $target = User::factory()->create(['roles' => ['cpp']]);
    $this->actingAs($actor)->get(route('users.edit', $target))->assertForbidden();
    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->call('setActive', false)->assertForbidden();
});

it('updates communities without losing approval information', function () {
    $actor      = User::factory()->create(['roles' => ['admin']]);
    $target     = User::factory()->create(['approved_by_user_id' => $actor->id]);
    $approvedAt = $target->approved_at;
    $first      = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $second     = Community::create(['name' => 'São João', 'abbreviation' => 'SJ', 'alias' => 'sao-joao']);
    $target->communities()->attach($first);

    Livewire::actingAs($actor)->test(Update::class, ['user' => $target])->set('communities', [$second->id])->call('save')->assertHasNoErrors();
    expect($target->communities()->pluck('communities.id')->all())->toBe([$second->id]);
    expect($target->fresh()->approved_at->equalTo($approvedAt))->toBeTrue();
    expect($target->fresh()->approved_by_user_id)->toBe($actor->id);
});

it('deactivates and reactivates accounts while retaining approval history', function () {
    $actor  = User::factory()->create(['roles' => ['secretary']]);
    $target = User::factory()->create(['approved_by_user_id' => $actor->id]);

    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->call('setActive', false)->assertHasNoErrors();
    expect($target->fresh()->statusLabel())->toBe('Inativo');
    $this->actingAs($target->fresh())->get(route('dashboard'))->assertRedirect(route('registration.pending'));
    $this->get(route('registration.pending'))->assertSee('Conta inativa');
    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->call('setActive', true)->assertHasNoErrors();
    $this->actingAs($target->fresh())->get(route('dashboard'))->assertOk();
    expect($target->fresh()->approved_by_user_id)->toBe($actor->id);
});

it('refuses reactivation of pending accounts without an approval', function () {
    $actor  = User::factory()->create(['roles' => ['admin']]);
    $target = User::factory()->pending()->create();
    expect(fn () => app(SetUserActiveAction::class)->handle($target, $actor, true))->toThrow(AuthorizationException::class);
    expect($target->fresh()->is_active)->toBeFalse();
});

it('protects the last active administrator against role removal and deactivation', function () {
    $admin = User::factory()->create(['roles' => ['admin']]);
    User::factory()->pending()->create(['roles' => ['admin']]);
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $admin->communities()->attach($community);

    Livewire::actingAs($admin)->test(Update::class, ['user' => $admin])->set('roles', ['member'])->call('save')->assertHasErrors('roles');
    Livewire::test(Show::class, ['user' => $admin])->call('setActive', false)->assertHasErrors('roles');
    expect($admin->fresh()->roles)->toBe(['admin']);
    expect($admin->fresh()->is_active)->toBeTrue();
});

it('allows administrator removal when another active administrator remains', function () {
    $actor     = User::factory()->create(['roles' => ['admin']]);
    $target    = User::factory()->create(['roles' => ['admin']]);
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $target->communities()->attach($community);
    Livewire::actingAs($actor)->test(Update::class, ['user' => $target])->set('roles', ['member'])->call('save')->assertHasNoErrors();
    $target->update(['roles' => ['admin']]);
    Livewire::test(Show::class, ['user' => $target])->call('setActive', false)->assertHasNoErrors();
    expect($target->fresh()->is_active)->toBeFalse();
    expect($actor->fresh()->is_active)->toBeTrue();
});

it('filters paginated users by state community and role with eager loaded communities', function () {
    $actor     = User::factory()->create(['roles' => ['admin']]);
    $pending   = User::factory()->pending()->create(['name' => 'Maria Pendente']);
    $active    = User::factory()->create(['roles' => ['pascom']]);
    $inactive  = User::factory()->create(['is_active' => false]);
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $pending->communities()->attach($community);

    $component = Livewire::actingAs($actor)->test(Index::class)->set('status', 'pending');
    expect($component->get('rows')->pluck('id')->all())->toBe([$pending->id]);
    expect($component->get('rows')->first()->relationLoaded('communities'))->toBeTrue();
    $component->set('status', 'active')->set('role', 'pascom');
    expect($component->get('rows')->pluck('id')->all())->toBe([$active->id]);
    $component->set('role', null)->set('status', 'inactive');
    expect($component->get('rows')->pluck('id')->all())->toBe([$inactive->id]);
    $component->set('status', '')->set('community', $community->id)->set('search', 'Maria');
    expect($component->get('rows')->pluck('id')->all())->toBe([$pending->id]);
});

it('keeps listing query counts bounded as the number of users grows', function () {
    $actor     = User::factory()->create(['roles' => ['admin']]);
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $target    = User::factory()->create();
    $target->communities()->attach($community);
    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::actingAs($actor)->test(Index::class)->set('quantity', 25);
    $small = count(DB::getQueryLog());
    User::factory()->count(20)->create()->each(fn (User $user) => $user->communities()->attach($community));
    DB::flushQueryLog();
    Livewire::actingAs($actor)->test(Index::class)->set('quantity', 25);
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($large)->toBeLessThanOrEqual($small + 2);
});

it('shows approval metadata and denies all management routes to members', function () {
    $actor  = User::factory()->create(['roles' => ['admin'], 'name' => 'Responsável']);
    $target = User::factory()->create(['approved_by_user_id' => $actor->id]);
    $this->actingAs($actor)->get(route('users.show', $target))->assertOk()->assertSee('Responsável');
    $this->get(route('users.edit', $target))->assertOk();
    $member = User::factory()->create();
    $this->actingAs($member)->get(route('users.show', $target))->assertForbidden();
    $this->get(route('users.edit', $target))->assertForbidden();
    expect(Gate::forUser($member)->allows('approve', $target))->toBeFalse();
});

it('does not replace the original approver when an already approved account is reviewed again', function () {
    $actor    = User::factory()->create(['roles' => ['admin']]);
    $original = User::factory()->create(['roles' => ['secretary']]);
    $target   = User::factory()->create(['approved_by_user_id' => $original->id]);
    expect(fn () => app(ApproveUserAction::class)->handle($target, $actor, ['member']))->toThrow(AuthorizationException::class);
    expect($target->fresh()->approved_by_user_id)->toBe($original->id);
});

it('keeps full page editing visible after saving over Livewire', function () {
    $actor     = User::factory()->create(['roles' => ['admin']]);
    $target    = User::factory()->create();
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $target->communities()->attach($community);

    Livewire::actingAs($actor)->test(Update::class, ['user' => $target, 'page' => true])
        ->set('name', 'Nome atualizado')->call('save')->assertHasNoErrors()
        ->assertSee('Voltar ao cadastro')->assertSet('page', true);
    expect($target->fresh()->name)->toBe('Nome atualizado');
});

it('requires communities and at least one role also when editing', function (string $property) {
    $actor     = User::factory()->create(['roles' => ['admin']]);
    $target    = User::factory()->create();
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);
    $target->communities()->attach($community);

    Livewire::actingAs($actor)->test(Update::class, ['user' => $target])->set($property, [])->call('save')->assertHasErrors($property);
    expect($target->communities()->pluck('communities.id')->all())->toBe([$community->id]);
    expect($target->fresh()->roles)->toBe(['member']);
})->with(['communities', 'roles']);

it('creates an approved administrative account with the selected communities and roles', function () {
    $actor     = User::factory()->create(['roles' => ['cpp']]);
    $community = Community::create(['name' => 'São Marcos', 'abbreviation' => 'SM', 'alias' => 'sao-marcos']);

    Livewire::actingAs($actor)->test(Create::class)->set([
        'name'        => 'Novo usuário', 'email' => 'novo@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        'communities' => [$community->id], 'roles' => ['member', 'cpp'],
    ])->call('save')->assertHasNoErrors()->assertDispatched('created');
    $created = User::where('email', 'novo@example.com')->firstOrFail();
    expect($created->is_active)->toBeTrue();
    expect($created->approved_by_user_id)->toBe($actor->id);
    expect($created->approved_at)->not->toBeNull();
    expect($created->roles)->toBe(['member', 'cpp']);
    expect($created->communities()->pluck('communities.id')->all())->toBe([$community->id]);
});

it('reactivates legacy accounts without fabricating approval data', function () {
    $actor  = User::factory()->active()->create(['roles' => ['secretary']]);
    $target = User::factory()->active()->create(['approved_at' => null, 'approved_by_user_id' => null]);

    Livewire::actingAs($actor)->test(Show::class, ['user' => $target])->call('setActive', false)->assertHasNoErrors();
    expect($target->fresh()->isPending())->toBeFalse();
    expect($target->fresh()->deactivated_at)->not->toBeNull();
    $rows = Livewire::test(Index::class)->set('status', 'inactive')->get('rows');
    expect($rows->pluck('id')->all())->toContain($target->id);
    Livewire::test(Show::class, ['user' => $target])->call('setActive', true)->assertHasNoErrors();
    expect($target->fresh()->is_active)->toBeTrue();
    expect($target->fresh()->deactivated_at)->toBeNull();
    expect($target->fresh()->approved_at)->toBeNull();
    expect($target->fresh()->approved_by_user_id)->toBeNull();
});
