<?php

namespace Kern\Media\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;
use Kern\Base\Facades\BaseHelper;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;

/**
 * @mixin MediaFile
 */
class FileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'basename' => File::basename($this->url),
            'url' => $this->url,
            'full_url' => $this->visibility === 'public' ? RvMedia::url($this->url) : null,
            'type' => $this->type,
            'icon' => $this->icon,
            'thumb' => $this->canGenerateThumbnails() ? RvMedia::getImageUrl($this->url, 'thumb') : null,
            'size' => $this->human_size,
            'mime_type' => $this->mime_type,
            'created_at' => BaseHelper::formatDate($this->created_at, 'Y-m-d H:i:s'),
            'updated_at' => BaseHelper::formatDate($this->updated_at, 'Y-m-d H:i:s'),
            'options' => $this->options,
            'folder_id' => $this->folder_id,
            'preview_url' => $this->preview_url,
            'preview_type' => $this->preview_type,
            'indirect_url' => $this->indirect_url,
            'alt' => $this->alt,
        ];
    }
}
