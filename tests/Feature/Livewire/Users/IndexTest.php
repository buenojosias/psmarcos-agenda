<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use Illuminate\Support\Arr;
use App\Livewire\Users\Index;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

beforeEach(function () {
    $this->auth = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Auth::login($this->auth);

    User::factory()->count(15)->create();
});

it('renders the users index component', function () {
    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewIs('livewire.users.index');
});

it('initializes with default settings', function () {
    Livewire::test(Index::class)
        ->assertSet('quantity', 10)
        ->assertSet('search', null)
        ->assertSet('sort', [
            'column'    => 'created_at',
            'direction' => 'desc',
        ]);
});

it('verifies component headers', function () {
    $component = Livewire::test(Index::class);

    $headers = [
        ['index' => 'name', 'label' => 'Nome'],
        ['index' => 'email', 'label' => 'E-mail'],
        ['index' => 'communities', 'label' => 'Comunidades', 'sortable' => false],
        ['index' => 'roles', 'label' => 'Perfis', 'sortable' => false],
        ['index' => 'status', 'label' => 'Status', 'sortable' => false],
        ['index' => 'created_at', 'label' => 'Cadastro'],
        ['index' => 'action', 'sortable' => false],
    ];

    $component->assertSet('headers', $headers);
});

it('fetches paginated users excluding authenticated user', function () {
    $rows = Livewire::test(Index::class)->get('rows');

    expect($rows)
        ->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($rows->total())
        ->toBe(15)
        ->and($rows->pluck('id'))->not()->toContain($this->auth->id);

});

it('filters users by search term', function () {
    $user = User::factory()->create([
        'name'  => 'John Unique Searchable',
        'email' => 'john.unique@example.com',
    ]);

    $component = Livewire::test(Index::class)
        ->set('search', 'John Unique');

    $rows = $component->get('rows');

    expect($rows->total())
        ->toBe(1)
        ->and($rows->first()->id)
        ->toBe($user->id);
});

it('supports searching by email', function () {
    $user = User::factory()->create([
        'name'  => 'Unique Search User',
        'email' => 'unique.searchable@example.com',
    ]);

    $component = Livewire::test(Index::class)->set('search', 'unique.searchable');

    $rows = $component->get('rows');

    expect($rows->total())
        ->toBe(1)
        ->and($rows->first()->id)
        ->toBe($user->id);
});

it('supports changing pagination quantity', function () {
    $component = Livewire::test(Index::class)->set('quantity', 5);

    $rows = $component->get('rows');

    expect($rows->perPage())
        ->toBe(5)
        ->and($rows->total())
        ->toBe(15);
});

it('supports sorting by different columns', function () {
    $component = Livewire::test(Index::class)
        ->set('sort', [
            'column'    => 'name',
            'direction' => 'asc',
        ]);

    $sort = $component->get('rows')->pluck('name')->toArray();

    expect($sort === array_values(Arr::sort($sort)))->toBeTrue();
});

it('handles empty search results', function () {
    $component = Livewire::test(Index::class)->set('search', 'non-existent-user');

    expect($component->get('rows')->total())->toBe(0);
});

it('lists community options by ascending id regardless of their names', function () {
    $firstCommunity  = Community::create(['name' => 'Zeladora', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $secondCommunity = Community::create(['name' => 'Alvorada', 'alias' => 'Capela', 'abbreviation' => 'CP']);

    Livewire::test(Index::class)
        ->assertSet('communityOptions', [
            ['label' => 'Sem comunidade', 'value' => 0],
            ['label' => 'Zeladora', 'value' => $firstCommunity->id],
            ['label' => 'Alvorada', 'value' => $secondCommunity->id],
        ]);
});

it('lists only users without any community when selecting no community', function () {
    $community    = Community::create(['name' => 'São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'SM']);
    $unlinkedUser = User::factory()->create(['name' => 'Community Filter Unlinked']);
    $linkedUser   = User::factory()->create(['name' => 'Community Filter Linked']);
    $linkedUser->communities()->attach($community);

    $component = Livewire::test(Index::class)
        ->set('search', 'Community Filter')
        ->call('setPage', 2)
        ->set('community', 0);

    expect($component->get('rows')->modelKeys())->toBe([$unlinkedUser->id]);
    expect($component->get('rows')->currentPage())->toBe(1);
});

it('filters by a community and restores all matching users when cleared', function () {
    $community      = Community::create(['name' => 'São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'SM']);
    $otherCommunity = Community::create(['name' => 'Santa Maria', 'alias' => 'Capela', 'abbreviation' => 'SA']);
    $unlinkedUser   = User::factory()->create(['name' => 'Community Filter Unlinked']);
    $linkedUser     = User::factory()->create(['name' => 'Community Filter Linked']);
    $otherUser      = User::factory()->create(['name' => 'Community Filter Other']);
    $linkedUser->communities()->attach($community);
    $otherUser->communities()->attach($otherCommunity);

    $component = Livewire::test(Index::class)
        ->set('search', 'Community Filter')
        ->set('community', $community->id);

    expect($component->get('rows')->modelKeys())->toBe([$linkedUser->id]);

    $component->set('community', 0)->set('community', null);

    expect($component->get('rows')->modelKeys())
        ->toEqualCanonicalizing([$unlinkedUser->id, $linkedUser->id, $otherUser->id]);
});

it('renders the status filter without a clear button', function (string $status) {
    $component = Livewire::test(Index::class)->set('status', $status);

    $document = new DOMDocument;
    $document->loadHTML($component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath        = new DOMXPath($document);
    $statusSelect = $xpath->query('//div[contains(@x-data, "\'status\'")]');

    expect($statusSelect)->toHaveCount(1);
    expect($xpath->query('.//button[@dusk="tallstackui_select_clear"]', $statusSelect->item(0)))
        ->toHaveCount(0);
})->with(['all statuses' => '', 'active status' => 'active']);

it('restores all statuses when selecting the all option', function () {
    $activeUser  = User::factory()->active()->create(['name' => 'Status Filter Active']);
    $pendingUser = User::factory()->pending()->create(['name' => 'Status Filter Pending']);

    $component = Livewire::test(Index::class)
        ->set('search', 'Status Filter')
        ->set('status', 'active');

    expect($component->get('rows')->modelKeys())->toBe([$activeUser->id]);

    $component->set('status', '')->assertSet('status', '');

    expect($component->get('rows')->modelKeys())
        ->toEqualCanonicalizing([$activeUser->id, $pendingUser->id]);
});
