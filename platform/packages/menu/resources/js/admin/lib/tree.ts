export type MenuItem = {
    key: string;
    parent: string;
    id?: string;
    title: string;
    url: string;
    reference_type: string;
    reference_id: string;
    icon_font: string;
    icon_image?: string;
    css_class: string;
    target: string;
};

export function descendants(items: MenuItem[], key: string): Set<string> {
    const result = new Set<string>([key]);
    let changed = true;
    while (changed) {
        changed = false;
        for (const item of items) {
            if (result.has(item.parent) && !result.has(item.key)) {
                result.add(item.key);
                changed = true;
            }
        }
    }
    return result;
}

export function serializeMenu(
    items: MenuItem[],
    parent = "",
    depth = 0,
): object[] {
    if (depth > 5) throw new Error("Menu depth cannot exceed five levels.");
    return items
        .filter((item) => item.parent === parent)
        .map((item, position) => ({
            menuItem: {
                ...(item.id ? { id: item.id } : {}),
                title: item.title,
                url: item.url,
                reference_type: item.reference_type,
                reference_id: item.reference_id || 0,
                icon_font: item.icon_font,
                ...(item.icon_image !== undefined
                    ? { icon_image: item.icon_image }
                    : {}),
                css_class: item.css_class,
                target: item.target,
                position,
            },
            children: serializeMenu(items, item.key, depth + 1),
        }));
}

export function reorderMenu(
    items: MenuItem[],
    key: string,
    direction: -1 | 1,
): MenuItem[] {
    const index = items.findIndex((item) => item.key === key);
    if (index < 0) return items;
    const siblings = items.filter(
        (item) => item.parent === items[index].parent,
    );
    const sibling =
        siblings[siblings.findIndex((item) => item.key === key) + direction];
    if (!sibling) return items;
    const other = items.findIndex((item) => item.key === sibling.key);
    const result = [...items];
    [result[index], result[other]] = [result[other], result[index]];
    return result;
}

export function moveMenuItem(
    items: MenuItem[],
    key: string,
    parent: string,
    before?: string,
): MenuItem[] {
    const item = items.find((item) => item.key === key);
    if (!item || (parent && !items.some((item) => item.key === parent)))
        return items;
    if (before === key && parent === item.parent) return items;
    if (descendants(items, key).has(parent))
        throw new Error(
            "A menu item cannot be moved into itself or its descendants.",
        );
    const result = items.filter((item) => item.key !== key);
    const position = before
        ? result.findIndex(
              (item) => item.key === before && item.parent === parent,
          )
        : -1;
    result.splice(position < 0 ? result.length : position, 0, {
        ...item,
        parent,
    });
    serializeMenu(result);
    return result;
}

export function cancelMenuItem(
    items: MenuItem[],
    key: string,
    snapshot: MenuItem,
): MenuItem[] {
    const current = items.find((item) => item.key === key);
    if (!current) return items;
    const blocked = descendants(items, key);
    const parent =
        snapshot.parent &&
        items.some((item) => item.key === snapshot.parent) &&
        !blocked.has(snapshot.parent)
            ? snapshot.parent
            : "";
    let restored: MenuItem[];
    try {
        restored =
            parent === current.parent
                ? items
                : moveMenuItem(items, key, parent);
    } catch {
        restored = current.parent === "" ? items : moveMenuItem(items, key, "");
    }
    return restored.map((item) =>
        item.key === key
            ? { ...snapshot, key: item.key, id: item.id, parent: item.parent }
            : item,
    );
}
