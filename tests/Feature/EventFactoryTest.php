<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Community;
use App\Models\EventDetail;

it('assigns an existing community to an internal event', function () {
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);

    $event = Event::factory()->create();

    expect($event->community_id)->toBe($community->id);
});

it('creates a community for an internal event when none exists', function () {
    $event = Event::factory()->create();

    expect($event->community_id)->not->toBeNull();
    $this->assertModelExists($event->community);
});

it('leaves external events without a community', function () {
    $event = Event::factory()->create(['is_external' => true]);

    expect($event->community_id)->toBeNull();
    $this->assertDatabaseCount('communities', 0);
});

it('creates events with a complement and their separate details', function () {
    $generated = Event::factory()->make();
    $event     = Event::factory()->create(['complement' => 'Uma noite de música']);

    $detail = EventDetail::factory()->for($event)->create();

    expect(array_key_exists('complement', $generated->getAttributes()))->toBeTrue();
    expect(array_key_exists('subtitle', EventDetail::factory()->raw()))->toBeFalse();
    expect($event->fresh()->complement)->toBe('Uma noite de música');
    expect($detail->event_id)->toBe($event->id);
});
