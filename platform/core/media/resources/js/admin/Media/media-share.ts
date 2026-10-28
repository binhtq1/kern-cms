export type MediaShareType = "url" | "indirect_url" | "html" | "markdown";
type ShareItem = {
    is_folder: boolean;
    name: string;
    alt?: string;
    full_url?: string | null;
    indirect_url?: string;
    mime_type?: string;
};

function safeUrl(value?: string | null): string {
    const url = value?.trim() ?? "";
    if (!url || /[\u0000-\u001f\u007f]/.test(url)) return "";
    if (/^https?:\/\//i.test(url) || /^\/(?!\/)/.test(url)) return url;
    return "";
}
function escapeHtml(value: string): string {
    return value.replace(
        /[&<>"']/g,
        (character) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[character]!,
    );
}
export function mediaShareText(
    items: ShareItem[],
    type: MediaShareType,
): string {
    return items
        .filter((item) => !item.is_folder)
        .flatMap((item) => {
            const url = safeUrl(
                type === "indirect_url" ? item.indirect_url : item.full_url,
            );
            if (!url) return [];
            const label = item.alt || item.name;
            if (type === "html") {
                return item.mime_type?.startsWith("image/")
                    ? `<img src="${escapeHtml(url)}" alt="${escapeHtml(label)}" />`
                    : `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`;
            }
            if (type === "markdown") {
                const title = label
                    .replace(/[\\`*_{}\[\]()<>!]/g, "\\$&")
                    .replace(/[\r\n]+/g, " ");
                const destination = url.replace(
                    /[\s<>"()\\]/g,
                    (character) =>
                        "%" +
                        character
                            .charCodeAt(0)
                            .toString(16)
                            .toUpperCase()
                            .padStart(2, "0"),
                );
                return `${item.mime_type?.startsWith("image/") ? "!" : ""}[${title}](${destination})`;
            }
            return url;
        })
        .join("\n");
}
