export type ContentFieldOption = {
    id?: string;
    label: string;
    value: string;
    order: number;
};

const identities = new WeakMap<ContentFieldOption, string>();
let nextIdentity = 0;

export function contentOptionIdentity(option: ContentFieldOption): string {
    if (option.id) return `saved:${option.id}`;
    let identity = identities.get(option);
    if (!identity) {
        identity = `new:${++nextIdentity}`;
        identities.set(option, identity);
    }
    return identity;
}

export function updateContentOption(
    option: ContentFieldOption,
    patch: Partial<ContentFieldOption>,
): ContentFieldOption {
    const updated = { ...option, ...patch };
    identities.set(updated, contentOptionIdentity(option));
    return updated;
}

export function moveContentOption(
    options: ContentFieldOption[],
    from: number,
    to: number,
): ContentFieldOption[] {
    if (
        from < 0 ||
        to < 0 ||
        from >= options.length ||
        to >= options.length ||
        from === to
    )
        return options;
    const next = [...options];
    const [option] = next.splice(from, 1);
    next.splice(to, 0, option);
    return next;
}
