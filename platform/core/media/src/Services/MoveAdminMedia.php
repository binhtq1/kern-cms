<?php

namespace Kern\Media\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use RuntimeException;
use Throwable;

class MoveAdminMedia
{
    protected array $copiedPaths = [];

    protected array $copiedFiles = [];

    public function execute(Request $request, bool $copy): void
    {
        $request->validate(['destination_id' => ['required', 'integer', 'min:0'], 'selected' => ['required', 'array', 'min:1', 'max:200']]);
        try {
            DB::transaction(function () use ($request, $copy): void {
                $destination = $request->integer('destination_id');
                $actor = $request->user();
                if ($destination) {
                    $folder = MediaFolder::query()->lockForUpdate()->findOrFail($destination);
                    abort_if(setting('user_can_only_view_own_media', false) && ! $actor->isSuperUser() && (string) $folder->user_id !== (string) $actor->getKey(), 403);
                }
                $selectedFolders = collect($request->input('selected'))->filter(fn (array $item): bool => (bool) $item['is_folder'])->pluck('id')->map(fn ($id): string => (string) $id)->all();
                $models = [];
                foreach ($request->input('selected') as $item) {
                    $isFolder = (bool) $item['is_folder'];
                    $model = $isFolder ? MediaFolder::query()->lockForUpdate()->findOrFail($item['id']) : MediaFile::query()->lockForUpdate()->findOrFail($item['id']);
                    $parent = $isFolder ? $model->parent_id : $model->folder_id;
                    $nested = false;
                    $seen = [];
                    while ($parent) {
                        if (in_array((string) $parent, $seen, true)) {
                            throw ValidationException::withMessages(['destination_id' => 'Invalid folder hierarchy.']);
                        }
                        $seen[] = (string) $parent;
                        if (in_array((string) $parent, $selectedFolders, true)) {
                            $nested = true;
                            break;
                        }
                        $parent = MediaFolder::query()->findOrFail($parent)->parent_id;
                    }
                    if ($nested) {
                        continue;
                    }
                    if ($isFolder) {
                        $parent = $destination;
                        $seen = [];
                        while ($parent) {
                            if ((string) $parent === (string) $model->getKey() || in_array((string) $parent, $seen, true)) {
                                throw ValidationException::withMessages(['destination_id' => 'A folder cannot be placed inside itself.']);
                            }
                            $seen[] = (string) $parent;
                            $parent = MediaFolder::query()->findOrFail($parent)->parent_id;
                        }
                    }
                    $models[] = $model;
                }
                foreach ($models as $model) {
                    if ($copy) {
                        if ($model instanceof MediaFolder) {
                            $this->copyFolder($model, $destination, (string) $actor->getKey());
                        } else {
                            $this->copyFile($model, $destination, (string) $actor->getKey());
                        }
                    } elseif ($model instanceof MediaFolder) {
                        if ((string) $model->parent_id === (string) $destination) {
                            continue;
                        }
                        $model->parent_id = $destination;
                        $model->name = MediaFolder::createName($model->name, $destination);
                        $model->slug = MediaFolder::createSlug($model->name, $destination);
                        $model->save();
                    } else {
                        $model->folder_id = $destination;
                        $model->save();
                    }
                }
            });
        } catch (Throwable $exception) {
            foreach ($this->copiedFiles as $file) {
                RvMedia::deleteFile($file);
            }
            Storage::delete($this->copiedPaths);
            throw $exception;
        }
    }

    protected function copyFolder(MediaFolder $source, int|string $destination, string $userId): void
    {
        $folder = MediaFolder::query()->create([
            'name' => MediaFolder::createName($source->name, $destination),
            'slug' => MediaFolder::createSlug($source->name, $destination),
            'parent_id' => $destination, 'user_id' => $userId, 'color' => $source->color,
        ]);
        foreach ($source->files()->get() as $file) {
            $this->copyFile($file, $folder->getKey(), $userId);
        }
        foreach (MediaFolder::query()->where('parent_id', $source->getKey())->get() as $child) {
            $this->copyFolder($child, $folder->getKey(), $userId);
        }
    }

    protected function copyFile(MediaFile $source, int|string $destination, string $userId): void
    {
        $directory = MediaFolder::getFullPath($destination);
        $path = ($directory ? $directory.'/' : '').Str::uuid().'.'.File::extension($source->url);
        if (! Storage::copy($source->url, $path)) {
            throw new RuntimeException('Unable to copy the selected file.');
        }
        $this->copiedPaths[] = $path;
        Storage::setVisibility($path, $source->visibility ?: 'public');
        $file = $source->replicate();
        $file->url = $path;
        $file->name = $source->folder_id == $destination ? $source->name.' (Copy)' : $source->name;
        $file->folder_id = $destination;
        $file->user_id = $userId;
        $file->save();
        $this->copiedFiles[] = $file;
        RvMedia::generateThumbnails($file);
    }
}
