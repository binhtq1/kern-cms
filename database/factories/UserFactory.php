<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kern\ACL\Models\User;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ];
    }

    public function superUser(): static
    {
        return $this->state(fn (): array => ['super_user' => true, 'manage_supers' => true]);
    }
}
