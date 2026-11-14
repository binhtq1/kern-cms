export type FieldItem = {
    id: string | null;
    title: string;
    slug: string;
    type: string;
    instructions: string;
    options: Record<string, string | number>;
    items: FieldItem[];
};
export type DisplayRule = { name: string; type: string; value: string };

export function normalizeFieldItems(items: FieldItem[]): FieldItem[] {
    return items.map((item) => ({
        ...item,
        id: item.id ? String(item.id) : null,
        options: item.options ?? {},
        items: normalizeFieldItems(item.items ?? []),
    }));
}

export function savedFieldGroupState(
    group: { items: FieldItem[]; rules?: DisplayRule[][] } | undefined,
    errors: Record<string, unknown> = {},
    failed = false,
) {
    if (
        failed ||
        !group ||
        !Array.isArray(group.items) ||
        Object.keys(errors).length
    )
        return null;
    return {
        items: normalizeFieldItems(group.items),
        rules: group.rules ?? [],
        deleted: [] as string[],
    };
}

const identities = new WeakMap<FieldItem, string>();
let nextIdentity = 0;
export function fieldItemIdentity(item: FieldItem): string {
    let identity = identities.get(item);
    if (!identity) {
        identity = `field-${++nextIdentity}`;
        identities.set(item, identity);
    }
    return identity;
}
export function updateFieldItem(
    item: FieldItem,
    patch: Partial<FieldItem>,
): FieldItem {
    const updated = { ...item, ...patch };
    identities.set(updated, fieldItemIdentity(item));
    return updated;
}
export function moveFieldItem(
    items: FieldItem[],
    source: string,
    target: string,
    after = false,
): FieldItem[] {
    const from = items.findIndex((item) => fieldItemIdentity(item) === source);
    const to = items.findIndex((item) => fieldItemIdentity(item) === target);
    if (from < 0 || to < 0 || from === to) return items;
    const next = [...items];
    const [item] = next.splice(from, 1);
    const insertion = to + (after ? 1 : 0);
    next.splice(insertion > from ? insertion - 1 : insertion, 0, item);
    return next;
}
export function normalizeFieldSlug(value: string): string {
    return value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/đ/g, "d")
        .replace(/\s+/g, "_")
        .replace(/[^\w\-]+/g, "")
        .replace(/\-\-+/g, "_")
        .replace(/^-+|-+$/g, "");
}
