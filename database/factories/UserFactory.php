<?php

namespace Database\Factories;

use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Administrador,
            'customer_id' => null,
            'branch_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an online store client.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Cliente,
        ]);
    }

    /**
     * Indicate that the user is internal staff.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Administrador,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Administrador,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Gerente,
        ]);
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Cajero,
        ]);
    }

    public function warehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Deposito,
        ]);
    }

    public function purchases(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Compras,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function branch(int $branchId): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branchId,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this;
    }
}
