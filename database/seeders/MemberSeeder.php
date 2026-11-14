<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kern\Base\Models\BaseModel;
use Kern\Member\Database\Seeders\MemberSeeder as BaseMemberSeeder;
use Kern\Member\Models\Member;

class MemberSeeder extends BaseMemberSeeder
{
    public function run(): void
    {
        foreach ($this->getMemberData() as $item) {
            unset($item['id']);
            $item['email'] = 'demo-member-'.Str::uuid().'@example.test';
            $item['password'] = Hash::make(Str::random(64));
            Member::query()->create($item);
        }
    }

    protected function getMemberData(): array
    {
        $files = $this->uploadFiles('members');

        $now = $this->now();

        $data = parent::getMemberData();

        $data[] = [
            'id' => BaseModel::getTypeOfId() === 'BIGINT' ? 11 : $this->fake()->uuid(),
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@example.test',
            'password' => Hash::make(Str::random(64)),
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($data as $index => $item) {
            if (! isset($files[$index])) {
                continue;
            }

            $file = $files[$index];

            $data[$index]['avatar_id'] = $file['error'] ? 0 : $file['data']->id;
        }

        return $data;
    }
}
