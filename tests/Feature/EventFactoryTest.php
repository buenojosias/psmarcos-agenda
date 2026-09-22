<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Community;

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
