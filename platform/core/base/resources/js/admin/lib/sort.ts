export function sortDestination(
    rect: { top: number; left: number; height: number },
    x: number,
    y: number,
    before: string,
    after: string,
    inside?: string,
): string {
    const fraction = (y - rect.top) / rect.height;
    return inside && x > rect.left + 64 && fraction > 0.25 && fraction < 0.75
        ? inside
        : fraction < 0.5
          ? before
          : after;
}

export function moveGroupedItem<
    T extends { key: string },
    G extends { id: string; items: T[] },
>(groups: G[], key: string, area: string, before?: string, created?: T): G[] {
    const item =
        created ??
        groups.flatMap((group) => group.items).find((item) => item.key === key);
    if (
        !item ||
        item.key === before ||
        !groups.some((group) => group.id === area)
    )
        return groups;
    return groups.map((group) => {
        const items = group.items.filter((widget) => widget.key !== item.key);
        if (group.id === area) {
            const index = items.findIndex((widget) => widget.key === before);
            items.splice(index < 0 ? items.length : index, 0, item);
        }
        return { ...group, items };
    });
}
