<?php

namespace Kern\CustomField\Repositories\Interfaces;

use Illuminate\Support\Collection;
use Kern\CustomField\Models\FieldItem;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface FieldItemInterface extends RepositoryContract
{
    public function deleteFieldItem(array|int|string|null $id): int;

    public function getGroupItems(int|string|null $groupId, int|string|null $parentId = null): Collection;

    public function updateWithUniqueSlug(int|string|null $id, array $data): ?FieldItem;
}
