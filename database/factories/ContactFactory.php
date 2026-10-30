<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kern\Contact\Models\Contact;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'status' => 'unread',
        ];
    }
}
