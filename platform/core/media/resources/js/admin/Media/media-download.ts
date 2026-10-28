type DownloadItem = {
    name: string;
    is_folder: boolean;
    full_url?: string | null;
    mime_type?: string;
};
function cleanFilename(value: string): string {
    return value
        .replace(/[\\/\u0000-\u001f\u007f:*?"<>|]/g, "_")
        .trim()
        .replace(/^[. ]+|[. ]+$/g, "");
}
export function mediaDownloadFilename(
    disposition: unknown,
    items: DownloadItem[],
    contentType: unknown,
): string {
    const header = typeof disposition === "string" ? disposition : "";
    const encoded = header.match(/(?:^|;)\s*filename\*\s*=\s*UTF-8''([^;]+)/i);
    let filename = "";
    if (encoded) {
        try {
            filename = decodeURIComponent(encoded[1].trim());
        } catch {
            /* Fall back to the regular filename. */
        }
    }
    if (!filename) {
        const regular = header.match(
            /(?:^|;)\s*filename\s*=\s*(?:"((?:\\.|[^"\\])*)"|([^;]+))/i,
        );
        filename = regular
            ? (regular[1]?.replace(/\\(.)/g, "$1") ?? regular[2].trim())
            : "";
    }
    if (cleanFilename(filename)) return cleanFilename(filename);
    const archive =
        items.length !== 1 ||
        items[0].is_folder ||
        String(contentType).includes("application/zip");
    const name =
        cleanFilename(items.length === 1 ? items[0].name : "media") || "media";
    if (archive) return /\.zip$/i.test(name) ? name : `${name}.zip`;
    if (/\.[a-z0-9]{1,10}$/i.test(name)) return name;
    let extension = "";
    try {
        extension =
            new URL(
                items[0].full_url ?? "",
                "https://media.invalid",
            ).pathname.match(/\.([a-z0-9]{1,10})$/i)?.[1] ?? "";
    } catch {
        /* A MIME fallback is available for invalid URLs. */
    }
    const mime = items[0].mime_type ?? String(contentType).split(";")[0];
    extension ||=
        (
            {
                "image/jpeg": "jpg",
                "image/png": "png",
                "image/webp": "webp",
                "image/gif": "gif",
                "image/svg+xml": "svg",
                "application/pdf": "pdf",
                "video/mp4": "mp4",
                "audio/mpeg": "mp3",
            } as Record<string, string>
        )[mime] ?? "";
    return extension ? `${name}.${extension}` : name;
}
