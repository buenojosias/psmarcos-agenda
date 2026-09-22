<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\Gate;
use App\Actions\UpdateEventDetailsAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates event details when the first field is filled', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    $changed = app(UpdateEventDetailsAction::class)->handle($event, [
        'subtitle' => 'Encontro das famílias',
    ], $user);

    expect($changed)->toBeTrue()
        ->and($event->detail()->firstOrFail()->subtitle)->toBe('Encontro das famílias');
});

it('updates only the provided fields', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();
    $event->detail()->create([
        'subtitle'     => 'Complemento anterior',
        'description'  => '<p>Descrição preservada</p>',
        'contact_name' => 'Contato preservado',
    ]);

    app(UpdateEventDetailsAction::class)->handle($event, [
        'subtitle' => 'Complemento atualizado',
    ], $user);

    $detail = $event->detail()->firstOrFail();

    expect($detail->subtitle)->toBe('Complemento atualizado')
        ->and($detail->description)->toBe('<p>Descrição preservada</p>')
        ->and($detail->contact_name)->toBe('Contato preservado');
});

it('treats visually empty editor html as absent content', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    $changed = app(UpdateEventDetailsAction::class)->handle($event, [
        'description' => '<p><br></p>&nbsp;',
    ], $user);

    expect($changed)->toBeFalse()
        ->and($event->detail()->exists())->toBeFalse()
        ->and($event->logs()->exists())->toBeFalse();
});

it('removes internal event details when every editable field becomes empty', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['is_external' => false]);
    $event->detail()->create(['subtitle' => 'Complemento']);

    app(UpdateEventDetailsAction::class)->handle($event, ['subtitle' => ''], $user);

    expect($event->detail()->exists())->toBeFalse();
});

it('preserves external event details when every editable field becomes empty', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['is_external' => true]);
    $event->detail()->create([
        'subtitle'                  => 'Complemento',
        'external_location_name'    => 'Salão externo',
        'external_location_address' => 'Rua Central, 100',
        'external_location_url'     => 'https://example.com/local',
    ]);

    app(UpdateEventDetailsAction::class)->handle($event, ['subtitle' => null], $user);

    $detail = $event->detail()->firstOrFail();

    expect($detail->subtitle)->toBeNull()
        ->and($detail->external_location_name)->toBe('Salão externo')
        ->and($detail->external_location_address)->toBe('Rua Central, 100')
        ->and($detail->external_location_url)->toBe('https://example.com/local');
});

it('keeps the event status when details are updated', function (EventStatusEnum $status) {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['status' => $status]);

    app(UpdateEventDetailsAction::class)->handle($event, ['subtitle' => 'Novo complemento'], $user);

    expect($event->refresh()->status)->toBe($status);
})->with([
    'pending'     => EventStatusEnum::PENDING,
    'confirmed'   => EventStatusEnum::CONFIRMED,
    'rescheduled' => EventStatusEnum::RESCHEDULED,
    'refused'     => EventStatusEnum::REFUSED,
]);

it('logs only fields that effectively changed', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();
    $event->detail()->create([
        'subtitle'    => 'Anterior',
        'description' => '<p>Sem alteração</p>',
    ]);

    app(UpdateEventDetailsAction::class)->handle($event, [
        'subtitle'    => 'Novo',
        'description' => '<p>Sem alteração</p>',
    ], $user);

    $log = $event->logs()->sole();

    expect($log->action)->toBe(EventLogActionEnum::UPDATED)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->changes)->toBe([
            'subtitle' => [
                'old' => 'Anterior',
                'new' => 'Novo',
            ],
        ]);
});

it('logs false when registration stops being required', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();
    $event->detail()->create(['registration_required' => true]);

    app(UpdateEventDetailsAction::class)->handle($event, [
        'registration_required' => false,
    ], $user);

    $log = $event->logs()->sole();

    expect($event->detail()->exists())->toBeFalse()
        ->and($log->changes)->toBe([
            'registration_required' => [
                'old' => true,
                'new' => false,
            ],
        ]);
});

it('does not persist or log when nothing effectively changed', function () {
    Gate::before(static fn (): bool => true);
    $user      = User::factory()->create();
    $event     = Event::factory()->create();
    $detail    = $event->detail()->create(['subtitle' => 'Sem alteração']);
    $updatedAt = $detail->updated_at->toISOString();

    $changed = app(UpdateEventDetailsAction::class)->handle($event, [
        'subtitle' => 'Sem alteração',
    ], $user);

    expect($changed)->toBeFalse()
        ->and($detail->refresh()->updated_at->toISOString())->toBe($updatedAt)
        ->and($event->logs()->exists())->toBeFalse();
});

it('does not persist changes from an unauthorized user', function () {
    Gate::before(static fn (): bool => false);
    $user  = User::factory()->create();
    $event = Event::factory()->create();
    $event->detail()->create(['subtitle' => 'Original']);

    expect(fn () => app(UpdateEventDetailsAction::class)->handle(
        $event,
        ['subtitle' => 'Tentativa sem permissão'],
        $user,
    ))->toThrow(AuthorizationException::class);

    expect($event->detail()->firstOrFail()->subtitle)->toBe('Original')
        ->and($event->logs()->exists())->toBeFalse();
});

it('does not update a canceled event', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['status' => EventStatusEnum::CANCELED]);
    $event->detail()->create(['subtitle' => 'Original']);

    expect(fn () => app(UpdateEventDetailsAction::class)->handle(
        $event,
        ['subtitle' => 'Tentativa cancelada'],
        $user,
    ))->toThrow(AuthorizationException::class);

    expect($event->detail()->firstOrFail()->subtitle)->toBe('Original')
        ->and($event->logs()->exists())->toBeFalse();
});
