<?php

namespace Kern\Block\Database\Seeders;

use Illuminate\Support\Str;
use Kern\Base\Supports\BaseSeeder;
use Kern\Block\Models\Block;

class StaticBlockSeeder extends BaseSeeder
{
    public function run(): void
    {
        Block::query()->truncate();

        $faker = $this->fake();

        for ($i = 0; $i < 5; $i++) {
            $name = $faker->name();

            Block::query()->create([
                'name' => $name,
                'alias' => Str::slug($name),
                'description' => $faker->text(50),
                'content' => $faker->text(500),
            ]);
        }
    }
}
