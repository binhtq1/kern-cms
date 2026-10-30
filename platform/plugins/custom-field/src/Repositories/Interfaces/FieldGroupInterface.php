<?php

namespace Kern\CustomField\Repositories\Interfaces;

use Illuminate\Support\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface FieldGroupInterface extends RepositoryContract
{
    public function getFieldGroups(array $condition = []): Collection;

    public function createFieldGroup(array $data);

    public function createOrUpdateFieldGroup(int|string|null $id, array $data);

    public function updateFieldGroup(int|string|null $id, array $data);

    public function getFieldGroupItems(
        $groupId,
        $parentId = null,
        $withValue = false,
        $morphClass = null,
        $morphId = null
    );
}
