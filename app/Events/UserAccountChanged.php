<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class UserAccountChanged implements ShouldDispatchAfterCommit
{
    /** @param array<string, mixed> $changes */
    public function __construct(public string $action, public User $user, public ?User $actor = null, public array $changes = []) {}
}
