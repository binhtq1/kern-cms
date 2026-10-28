<?php

namespace Kern\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Media\Models\MediaFile;

class MediaFileRenaming
{
    use Dispatchable;

    public function __construct(public MediaFile $file, public string $newName, public bool $renameOnDisk) {}
}
