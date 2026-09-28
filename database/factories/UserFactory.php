<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => '$2y$04$pH3Ri/itPyiEbheC3kPj/eMf2AYCvroUo5ZZ1HmAksGA6W2HCw9B2', // Test123!
            'is_active'         => true,
            'roles'             => ['member'],
            'approved_at'       => now(),
            'remember_token'    => Str::random(10),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => true, 'approved_at' => now(), 'deactivated_at' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false, 'approved_at' => null, 'approved_by_user_id' => null, 'deactivated_at' => null, 'roles' => ['member']]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret'         => Fortify::currentEncrypter()->encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
            'two_factor_confirmed_at'   => now(),
        ]);
    }
}
