<?php

namespace Kern\Media\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Kern\Base\Facades\BaseHelper;
use Kern\Media\Models\MediaFolder;

/**
 * @mixin MediaFolder
 */
class FolderResource extends JsonResource
{
    protected Collection $files;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'created_at' => BaseHelper::formatDate($this->created_at, 'Y-m-d H:i:s'),
            'updated_at' => BaseHelper::formatDate($this->updated_at, 'Y-m-d H:i:s'),
            ...isset($this->files) ? [
                'files' => FileResource::collection($this->files),
            ] : [],
        ];
    }

    public function withFiles(Collection $files): self
    {
        $this->files = $files;

        return $this;
    }
}
