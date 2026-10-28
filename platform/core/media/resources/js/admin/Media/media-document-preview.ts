export function mediaDocumentPreview(
    item: {
        type?: string;
        preview_url?: string | null;
        preview_type?: string | null;
    },
    origin: string,
): { url: string; popup: boolean } | null {
    if (item.type !== "document" || !item.preview_url) return null;
    try {
        const url = new URL(item.preview_url, origin);
        if (
            !["http:", "https:"].includes(url.protocol) ||
            url.username ||
            url.password
        )
            return null;
        return { url: url.href, popup: item.preview_type === "popup" };
    } catch {
        return null;
    }
}
