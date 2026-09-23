<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Enums\EventStatusEnum;
use App\Livewire\Events\Notes;

it('lists notes from oldest to newest with the sender and submission time', function () {
    $user   = User::factory()->create(['name' => 'Josias', 'roles' => ['admin'], 'is_active' => true]);
    $author = User::factory()->create(['name' => 'Ana']);
    $event  = Event::factory()->create(['status' => EventStatusEnum::CONFIRMED]);

    $this->travelTo('2026-10-10 09:15:00');
    $event->notes()->create(['user_id' => $author->id, 'content' => 'Primeira nota']);
    $this->travelTo('2026-10-10 10:30:00');
    $event->notes()->create(['user_id' => $user->id, 'content' => 'Segunda nota']);

    Livewire::actingAs($user)
        ->test(Notes::class, ['event' => $event])
        ->assertSeeInOrder(['Ana', '10/10/2026 09:15', 'Primeira nota', 'Mim', '10/10/2026 10:30', 'Segunda nota']);
});

it('saves notes of up to 200 characters and rejects longer content', function () {
    $user    = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event   = Event::factory()->create();
    $content = str_repeat('a', 200);

    $component = Livewire::actingAs($user)
        ->test(Notes::class, ['event' => $event])
        ->set('content', $content)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('content', '')
        ->assertSee($content);

    $component->set('content', str_repeat('a', 201))
        ->call('save')
        ->assertHasErrors(['content' => 'max']);

    $component->set('content', '   ')
        ->call('save')
        ->assertHasErrors(['content' => 'required']);

    expect($event->notes()->count())->toBe(1)
        ->and($event->notes()->sole()->content)->toBe($content);
});

it('lets an active priest write on an unrelated pending event', function () {
    $priest = User::factory()->create(['roles' => ['priest'], 'is_active' => true]);
    $event  = Event::factory()->for(Group::factory())->create(['status' => EventStatusEnum::PENDING]);

    Livewire::actingAs($priest)
        ->test(Notes::class, ['event' => $event])
        ->set('content', 'Acompanhar a preparação.')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->notes()->sole()->user_id)->toBe($priest->id);
});

it('forbids an unrelated member and an inactive priest from opening notes', function (array $roles, bool $isActive) {
    $user  = User::factory()->create(['roles' => $roles, 'is_active' => $isActive]);
    $event = Event::factory()->for(Group::factory())->create(['status' => EventStatusEnum::CONFIRMED]);

    Livewire::actingAs($user)->test(Notes::class, ['event' => $event])->assertForbidden();
})->with([
    'unrelated member' => [['member'], true],
    'inactive priest'  => [['priest'], false],
]);

it('rechecks group membership before saving a note', function () {
    $user  = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group = Group::factory()->create();
    $group->users()->attach($user);
    $event     = Event::factory()->for($group)->create(['status' => EventStatusEnum::CONFIRMED]);
    $component = Livewire::actingAs($user)
        ->test(Notes::class, ['event' => $event])
        ->set('content', 'Tentativa sem acesso');

    $group->users()->detach($user);

    $component->call('save')->assertForbidden();
    expect($event->notes()->exists())->toBeFalse();
});
