<?php

namespace Kern\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Media\Models\MediaFolder;

class MediaFolderRenamed
{
    use Dispatchable;

    public function __construct(public MediaFolder $folder) {}
}
