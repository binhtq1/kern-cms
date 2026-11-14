<?php

namespace Kern\Gallery\Database\Traits;

use Kern\ACL\Models\User;
use Kern\Gallery\Models\Gallery;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Slug\Facades\SlugHelper;

trait HasGallerySeeder
{
    protected function createGalleries(array $galleries, array $images = [], bool $truncate = true): void
    {
        if ($truncate) {
            Gallery::query()->truncate();
            GalleryMeta::query()->truncate();
        }

        $faker = $this->fake();
        $userId = User::query()->value('id');

        foreach ($galleries as $item) {
            if (! isset($item['description'])) {
                $item['description'] = $faker->text(150);
            }

            $item['user_id'] = $userId;

            /**
             * @var Gallery $gallery
             */
            $gallery = Gallery::query()->create($item);

            SlugHelper::createSlug($gallery);

            $this->createMetadata($gallery, $item);

            GalleryMeta::query()->create([
                'images' => $images,
                'reference_id' => $gallery->getKey(),
                'reference_type' => Gallery::class,
            ]);
        }
    }
}
