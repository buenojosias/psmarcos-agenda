<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Users\Delete;

it('renders the retired delete component without a delete button', function () {
    $this->actingAs(User::factory()->active()->create(['roles' => ['admin']]));
    $target = User::factory()->create();
    Livewire::test(Delete::class, ['user' => $target])->assertOk()->assertDontSee('trash');
});

it('refuses legacy physical deletion calls', function (string $method) {
    $this->actingAs(User::factory()->active()->create(['roles' => ['admin']]));
    $target = User::factory()->create();
    Livewire::test(Delete::class, ['user' => $target])->call($method)->assertForbidden();
    $this->assertModelExists($target);
})->with(['confirm', 'delete']);
