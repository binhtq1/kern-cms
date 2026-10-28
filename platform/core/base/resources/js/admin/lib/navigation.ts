import type { NavigationItem } from "../types";

export function activeNavigation(
    items: NavigationItem[],
    current: string,
    dashboardUrl: string,
): NavigationItem[] {
    const base = new URL(current, "https://admin.invalid");
    const path = base.pathname.replace(/\/$/, "");
    const dashboardPath = new URL(dashboardUrl, base).pathname.replace(
        /\/$/,
        "",
    );
    let best: NavigationItem | undefined;
    let length = -1;
    const find = (entries: NavigationItem[], destination = path) => {
        for (const item of entries) {
            if (item.url && !item.url.startsWith("#")) {
                const url = new URL(item.url, base);
                const candidate = url.pathname.replace(/\/$/, "");
                if (
                    url.origin === base.origin &&
                    candidate.length > length &&
                    (destination === candidate ||
                        (candidate !== dashboardPath &&
                            destination.startsWith(`${candidate}/`)))
                ) {
                    best = item;
                    length = candidate.length;
                }
            }
            find(item.children ?? [], destination);
        }
    };
    find(items);
    if (!best) {
        const sections: Record<string, string> = {
            translations: "settings",
            "audit-logs": "system",
            "request-logs": "system",
            "blog/tools/data-synchronize": "tools/data-synchronize",
        };
        for (const [section, owner] of Object.entries(sections)) {
            const prefix = `${dashboardPath}/${section}`;
            if (path === prefix || path.startsWith(`${prefix}/`)) {
                find(items, `${dashboardPath}/${owner}`);
                break;
            }
        }
    }
    const mark = (entries: NavigationItem[]): NavigationItem[] =>
        entries.map((item) => {
            const children = mark(item.children ?? []);
            return {
                ...item,
                active: item === best || children.some((child) => child.active),
                children,
            };
        });
    return mark(items);
}
