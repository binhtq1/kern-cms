<?php

namespace Kern\CustomField\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Kern\CustomField\Models\FieldItem;
use Kern\CustomField\Repositories\Interfaces\FieldItemInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class FieldItemRepository extends RepositoryAbstract implements FieldItemInterface
{
    public function deleteFieldItem(array|int|string|null $id): int
    {
        return $this->model->whereIn('id', (array) $id)->get()->sum(fn (FieldItem $field): int => $field->delete() ? 1 : 0);
    }

    public function getGroupItems(int|string|null $groupId, int|string|null $parentId = null): Collection
    {
        return $this->model
            ->where([
                'field_group_id' => $groupId,
                'parent_id' => $parentId,
            ])
            ->orderBy('order')
            ->get();
    }

    public function updateWithUniqueSlug(int|string|null $id, array $data): ?FieldItem
    {
        $data['slug'] = $this->makeUniqueSlug($id, $data['parent_id'], $data['slug'], $data['position']);

        return $this->createOrUpdate($data, compact('id'));
    }

    protected function makeUniqueSlug(
        int|string|null $id,
        int|string|null $parentId,
        ?string $slug,
        int $position
    ): ?string {
        $isExist = $this->getFirstBy([
            'slug' => $slug,
            'parent_id' => $parentId,
        ]);

        if ($isExist && (int) $id != (int) $isExist->id) {
            return $slug.'_'.time().$position;
        }

        return $slug;
    }
}
