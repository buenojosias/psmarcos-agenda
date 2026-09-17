<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Communities\Show;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;

it('shows community details and only its own groups', function () {
    $this->withoutVite();
    $user      = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Comunidade São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'MT', 'address' => 'Praça central']);
    $other     = Community::create(['name' => 'Outra', 'alias' => 'Capela', 'abbreviation' => 'CP']);
    $group     = Group::factory()->create(['community_id' => $community->id, 'name' => 'Grupo da Matriz']);
    Group::factory()->create(['community_id' => $other->id, 'name' => 'Grupo da Capela']);
    Group::factory()->create(['community_id' => null, 'name' => 'Grupo Paroquial']);

    $this->actingAs($user)->get(route('communities.show', ['community' => $community, 'tab' => 'groups']))
        ->assertOk()->assertSee('Comunidade São Marcos')->assertSee('Matriz')
        ->assertSee('MT')->assertSee('Praça central')->assertSee('Grupo da Matriz')
        ->assertSee(route('groups.show', $group))->assertDontSee('Grupo da Capela')
        ->assertDontSee('Grupo Paroquial')->assertDontSee('Editar comunidade');
});

it('shows an empty groups message and refreshes edited community details', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $component = Livewire::actingAs($user)->test(Show::class, ['community' => $community])
        ->set('tab', 'groups')->assertSee('Nenhum grupo está vinculado a esta comunidade.')->assertSee('Editar comunidade');
    $community->update(['name' => 'Nome atualizado']);

    $component->call('refreshCommunity')->assertSee('Nome atualizado');
});

it('requires authentication and activity to view a community', function () {
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $this->get(route('communities.show', $community))->assertRedirect(route('login'));
    $user = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => false]);

    $this->actingAs($user)->get(route('communities.show', $community))->assertForbidden();
    Livewire::actingAs($user)->test(Show::class, ['community' => $community])->assertForbidden();
});

it('defaults to information and synchronizes tab changes with Livewire', function () {
    $user      = User::factory()->create(['is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->test(Show::class, ['community' => $community])
        ->assertSet('tab', 'information')
        ->assertSee(['Informações', 'Grupos', 'Espaços', 'Calendário'])
        ->set('tab', 'groups')->assertSet('tab', 'groups')
        ->set('tab', 'spaces')->assertSet('tab', 'spaces')
        ->assertSee('A gestão dos espaços da comunidade será disponibilizada em breve.')
        ->set('tab', 'calendar')->assertSet('tab', 'calendar')
        ->assertSee('O calendário da comunidade será disponibilizado em breve.')
        ->set('tab', 'invalid')->assertSet('tab', 'information');
});

it('restores valid tabs from the URL and falls back for invalid values', function (mixed $tab, string $expected) {
    $user      = User::factory()->create(['is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->withQueryParams(['tab' => $tab])
        ->test(Show::class, ['community' => $community])
        ->assertSet('tab', $expected);
})->with([
    ['information', 'information'],
    ['groups', 'groups'],
    ['spaces', 'spaces'],
    ['calendar', 'calendar'],
    ['invalid', 'information'],
    ['', 'information'],
    [['groups'], 'information'],
]);

it('queries groups only while their tab is active', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    Group::factory()->create(['community_id' => $community->id, 'name' => 'Grupo carregado sob demanda']);
    $groupQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$groupQueries): void {
        if (preg_match('/from ["`]?groups["`]?/i', $query->sql)) {
            $groupQueries++;
        }
    });

    $component = Livewire::actingAs($user)->test(Show::class, ['community' => $community])
        ->assertDontSee('Grupo carregado sob demanda')
        ->set('tab', 'spaces')->set('tab', 'calendar');
    expect($groupQueries)->toBe(0);

    $component->set('tab', 'groups')->assertSee('Grupo carregado sob demanda');
    expect($groupQueries)->toBe(1);

    $component->set('tab', 'information')->assertDontSee('Grupo carregado sob demanda')
        ->set('tab', 'spaces')->set('tab', 'calendar');
    expect($groupQueries)->toBe(1);

    $component->set('tab', 'groups')->assertSee('Grupo carregado sob demanda');
    expect($groupQueries)->toBe(2);
});
