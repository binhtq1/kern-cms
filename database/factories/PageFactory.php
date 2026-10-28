<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kern\Page\Models\Page;

/** @extends Factory<Page> */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return ['name' => fake()->sentence(3), 'status' => 'published', 'template' => 'default'];
    }
}
