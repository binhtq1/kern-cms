<?php

namespace Kern\Base\GlobalSearch\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\GlobalSearch\GlobalSearchableProvider;
use Kern\Base\GlobalSearch\GlobalSearchableResult;

class PanelSectionProvider extends GlobalSearchableProvider
{
    protected array $cachedGroupNames = [];

    public function search(string $keyword): Collection
    {
        $items = [];

        foreach (PanelSectionManager::getAllSections() as $group => $sections) {
            foreach ($sections as $section) {
                $children = $section->getItems();

                if (! empty($children)) {
                    foreach ($children as $item) {
                        if (
                            (
                                $this->stringContains($item->getTitle(), $keyword)
                                || $this->stringContains($item->getDescription(), $keyword)
                            ) && ! empty($item->getUrl())
                        ) {
                            $key = Str::slug("{$item->getTitle()}-{$item->getUrl()}");

                            if (array_key_exists($key, $items)) {
                                continue;
                            }

                            $items[$key] = new GlobalSearchableResult(
                                title: $item->getTitle(),
                                description: $item->getDescription(),
                                parents: [
                                    $this->getGroupName($group),
                                    $section->getTitle(),
                                ],
                                url: $item->getUrl(),
                            );
                        }
                    }
                }
            }
        }

        return collect($items);
    }

    protected function getGroupName(string $groupId): string
    {
        return $this->cachedGroupNames[$groupId] ??= PanelSectionManager::group($groupId)->getGroupName();
    }
}
