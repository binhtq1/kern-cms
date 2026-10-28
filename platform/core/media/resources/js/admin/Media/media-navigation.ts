export type MediaNavigation = {
    folder: string;
    view: string;
    sort: string;
    filter: string;
    grid: boolean;
};
export const defaultMediaNavigation: MediaNavigation = {
    folder: "0",
    view: "all_media",
    sort: "created_at-desc",
    filter: "everything",
    grid: true,
};
export function restoreMediaNavigation(
    raw: string | null,
    key: string,
): MediaNavigation {
    try {
        const stored = JSON.parse(raw ?? "null");
        if (!stored || stored.version !== 1 || stored.app_key !== key)
            return { ...defaultMediaNavigation };
        const saved = stored.navigation;
        if (!saved || typeof saved !== "object")
            return { ...defaultMediaNavigation };
        const view = ["all_media", "recent", "favorites", "trash"].includes(
            saved.view,
        )
            ? saved.view
            : defaultMediaNavigation.view;
        return {
            folder:
                (view === "all_media" || view === "trash") &&
                /^(0|[1-9]\d{0,19})$/.test(String(saved.folder))
                    ? String(saved.folder)
                    : "0",
            view,
            sort: [
                "name-asc",
                "name-desc",
                "created_at-asc",
                "created_at-desc",
                "size-asc",
                "size-desc",
            ].includes(saved.sort)
                ? saved.sort
                : defaultMediaNavigation.sort,
            filter: [
                "everything",
                "image",
                "document",
                "video",
                "audio",
            ].includes(saved.filter)
                ? saved.filter
                : defaultMediaNavigation.filter,
            grid: typeof saved.grid === "boolean" ? saved.grid : true,
        };
    } catch {
        return { ...defaultMediaNavigation };
    }
}
export function persistMediaNavigation(
    navigation: MediaNavigation,
    key: string,
): string {
    return JSON.stringify({ version: 1, app_key: key, navigation });
}
export function mediaFolderUnavailable(
    folder: string | number,
    status?: number,
    breadcrumbs?: { id: string | number }[],
): boolean {
    return (
        String(folder) !== "0" &&
        (status === 403 ||
            status === 404 ||
            (Array.isArray(breadcrumbs) &&
                !breadcrumbs.some(
                    (item) => String(item.id) === String(folder),
                )))
    );
}
