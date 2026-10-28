<?php

namespace Kern\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Media\Models\MediaFolder;

class MediaFolderRenaming
{
    use Dispatchable;

    public function __construct(public MediaFolder $file, public string $newName, public bool $renameOnDisk) {}
}
