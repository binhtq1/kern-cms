<?php

namespace Kern\Media\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface MediaFileInterface extends RepositoryContract
{
    public function createName(string $name, int|string|null $folder): string;

    public function createSlug(string $name, string $extension, ?string $folderPath): string;

    public function getFilesByFolderId(int|string $folderId, array $params = [], bool $withFolders = true, array $folderParams = []);

    public function getTrashed(int|string $folderId, array $params = [], bool $withFolders = true, array $folderParams = []): Collection;

    public function emptyTrash(): bool;
}
