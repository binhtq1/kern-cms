<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Use cms:user:create for the administrator, then db:seed --class=RippleDemoSeeder for optional demo content on an empty site.');
    }
}
