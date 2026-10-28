export function draftSnapshot(value: unknown): string {
    return JSON.stringify(value);
}
export function isSchemaRefresh(
    visit: { only?: string[]; preserveState?: unknown; url: URL },
    currentUrl: string,
): boolean {
    if (
        !visit.only?.length ||
        !visit.only.every((key) => key === "customFieldGroups") ||
        !visit.preserveState
    )
        return false;
    const current = new URL(currentUrl);
    if (
        visit.url.origin !== current.origin ||
        visit.url.pathname !== current.pathname
    )
        return false;
    const query = (url: URL) => {
        const params = new URLSearchParams(url.search);
        params.delete("template");
        params.sort();
        return params.toString();
    };
    return query(visit.url) === query(current);
}
