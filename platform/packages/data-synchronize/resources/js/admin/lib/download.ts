export async function downloadError(
    data: unknown,
    fallback: string,
): Promise<string> {
    try {
        const result =
            data instanceof Blob ? JSON.parse(await data.text()) : data;
        if (
            result &&
            typeof result === "object" &&
            "message" in result &&
            typeof result.message === "string" &&
            result.message
        )
            return result.message;
    } catch {
        // Non-JSON error pages must not become downloadable export files.
    }
    return fallback;
}

export function downloadFilename(
    disposition: unknown,
    fallback: string,
): string {
    const header = typeof disposition === "string" ? disposition : "";
    const encoded = /filename\*=UTF-8''([^;]+)/i.exec(header)?.[1];
    if (encoded) {
        try {
            return decodeURIComponent(encoded);
        } catch {
            /* Use the plain filename when an encoded header is malformed. */
        }
    }
    return (
        /filename="([^"]+)"|filename=([^;]+)/i
            .exec(header)
            ?.slice(1)
            .find(Boolean)
            ?.trim() || fallback
    );
}
