<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'password',
        'roles',
        'is_active',
        'created_by_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'roles'             => 'array',
        'is_active'         => 'boolean',
    ];

    // Retorna array (sempre) — facilita uso nas views
    public function getRolesArray(): array
    {
        return $this->roles ?? [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRolesArray(), true);
    }

    /** aceita string ou array */
    public function hasAnyRole(array|string $roles): bool
    {
        $roles = (array) $roles;
        return count(array_intersect($roles, $this->getRolesArray())) > 0;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'created_by_user_id');
    }

    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('is_coordinator');
    }
}
