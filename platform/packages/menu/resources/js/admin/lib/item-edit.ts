import type { MenuItem } from "./tree";
export type MenuReference = {
    value: string;
    label: string;
    type: string;
    id: string;
    title: string;
    url: string;
};
export function selectMenuReferences(
    previous: MenuReference[],
    visible: MenuReference[],
    values: string[],
): MenuReference[] {
    const available = new Map(
        [...previous, ...visible].map((item) => [item.value, item]),
    );
    return [...new Set(values)].flatMap((value) => {
        const item = available.get(value);
        return item ? [item] : [];
    });
}
export function referenceMenuItems(
    references: MenuReference[],
    createKey: () => string,
    iconImage: boolean,
): MenuItem[] {
    return selectMenuReferences(
        [],
        references,
        references.map((item) => item.value),
    ).map((item) => ({
        key: createKey(),
        parent: "",
        title: item.title,
        url: item.url,
        reference_type: item.type,
        reference_id: item.id,
        icon_font: "",
        ...(iconImage ? { icon_image: "" } : {}),
        css_class: "",
        target: "_self",
    }));
}
