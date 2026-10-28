<?php

namespace Kern\Slug\Listeners;

use Kern\Slug\Models\Slug;

class TruncateSlug
{
    public function handle(): void
    {
        Slug::query()->truncate();
    }
}
