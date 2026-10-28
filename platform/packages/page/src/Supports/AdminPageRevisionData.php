<?php

namespace Kern\Page\Supports;

use Illuminate\Support\Str;
use Kern\ACL\Models\User;
use Kern\Page\Models\Page;
use Kern\Revision\Revision;

class AdminPageRevisionData
{
    /**
     * @return array<int, array{id: string, field: string, label: string, old_value: ?string, new_value: ?string, author: ?string, created_at: ?string}>
     */
    public function forPage(Page $page): array
    {
        if (! $page->exists || ! in_array(Page::class, config('packages.revision.general.supported', []), true)) {
            return [];
        }

        $history = $page->revisionHistory()->orderByDesc('created_at')->orderByDesc('id')->get();
        $userIds = $history->pluck('user_id');

        foreach ($history as $revision) {
            if ($revision->key === 'user_id') {
                $userIds->push($revision->old_value, $revision->new_value);
            }
        }

        $users = User::query()->whereKey($userIds->filter()->unique()->values()->all())->get(['id', 'first_name', 'last_name'])->keyBy('id');

        return $history->map(function (Revision $revision) use ($users): array {
            return [
                'id' => (string) $revision->getKey(),
                'field' => $revision->key,
                'label' => Str::headline($revision->fieldName()),
                'old_value' => $revision->key === 'user_id'
                    ? $users->get($revision->old_value)?->name ?? $revision->old_value
                    : $revision->oldValue(),
                'new_value' => $revision->key === 'user_id'
                    ? $users->get($revision->new_value)?->name ?? $revision->new_value
                    : $revision->newValue(),
                'author' => $users->get($revision->user_id)?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
            ];
        })->all();
    }
}
