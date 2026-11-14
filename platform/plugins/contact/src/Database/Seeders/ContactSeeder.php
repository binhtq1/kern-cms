<?php

namespace Kern\Contact\Database\Seeders;

use Kern\Base\Supports\BaseSeeder;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Models\Contact;

class ContactSeeder extends BaseSeeder
{
    public function run(): void
    {
        Contact::query()->truncate();

        $faker = $this->fake();

        for ($i = 0; $i < 10; $i++) {
            Contact::query()->create([
                'name' => $faker->name(),
                'email' => $faker->safeEmail(),
                'phone' => $faker->phoneNumber(),
                'address' => $faker->address(),
                'subject' => $faker->text(50),
                'content' => $faker->text(500),
                'status' => $faker->randomElement([ContactStatusEnum::READ, ContactStatusEnum::UNREAD]),
            ]);
        }
    }
}
