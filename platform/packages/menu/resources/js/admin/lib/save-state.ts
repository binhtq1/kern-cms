import type { MenuItem } from "./tree";

export function reconcileMenuSave(
    submitted: MenuItem[],
    saved: MenuItem[],
    current: MenuItem[],
) {
    const ids = new Map<string, string>();
    const match = (submittedParent: string, savedParent: string) => {
        const submittedChildren = submitted.filter(
            (item) => item.parent === submittedParent,
        );
        const savedChildren = saved.filter(
            (item) => item.parent === savedParent,
        );
        submittedChildren.forEach((item, index) => {
            const serverItem = savedChildren[index];
            if (!serverItem) return;
            if (serverItem.id) ids.set(item.key, serverItem.id);
            match(item.key, serverItem.key);
        });
    };
    match("", "");
    const identify = (items: MenuItem[]) =>
        items.map((item) =>
            ids.has(item.key) ? { ...item, id: ids.get(item.key) } : item,
        );
    return { baseline: identify(submitted), current: identify(current) };
}

export function mergeSavedFields<T extends Record<string, unknown>>(
    submitted: T,
    saved: T,
    current: T,
): T {
    return Object.fromEntries(
        Object.keys(current).map((key) => [
            key,
            JSON.stringify(current[key]) === JSON.stringify(submitted[key])
                ? saved[key]
                : current[key],
        ]),
    ) as T;
}
