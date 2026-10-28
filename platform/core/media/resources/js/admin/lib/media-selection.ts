import type { Item } from "./media-types";

export const mediaItemKey = (item: Item): string =>
    `${item.is_folder ? "folder" : "file"}-${item.id}`;

export function toggleMediaItem(selected: Item[], item: Item): Item[] {
    const key = mediaItemKey(item);
    return selected.some((entry) => mediaItemKey(entry) === key)
        ? selected.filter((entry) => mediaItemKey(entry) !== key)
        : [...selected, item];
}

export function selectMediaItems(
    items: Item[],
    selected: Item[],
    item: Item,
    modifiers: { shiftKey?: boolean; ctrlKey?: boolean; metaKey?: boolean },
    anchor: string | null,
): { selected: Item[]; anchor: string | null; openFolder: boolean } {
    if (
        item.is_folder &&
        !modifiers.shiftKey &&
        !modifiers.ctrlKey &&
        !modifiers.metaKey
    )
        return { selected, anchor, openFolder: true };
    const from = items.findIndex((entry) => mediaItemKey(entry) === anchor);
    const to = items.findIndex(
        (entry) => mediaItemKey(entry) === mediaItemKey(item),
    );
    if (modifiers.shiftKey && from >= 0 && to >= 0)
        return {
            selected: items.slice(Math.min(from, to), Math.max(from, to) + 1),
            anchor,
            openFolder: false,
        };
    if (modifiers.ctrlKey || modifiers.metaKey)
        return {
            selected: toggleMediaItem(selected, item),
            anchor,
            openFolder: false,
        };
    return { selected: [item], anchor: mediaItemKey(item), openFolder: false };
}
