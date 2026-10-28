export function validImageSource(value: string): string | null {
    const source = value.trim();
    if (!source || /[\u0000-\u0020\\]/.test(source) || source.startsWith("//"))
        return null;
    if (/^https?:\/\//i.test(source)) {
        try {
            const url = new URL(source);
            return url.username || url.password ? null : url.href;
        } catch {
            return null;
        }
    }
    return source.startsWith("/") && !source.startsWith("//") ? source : null;
}

export function mediaPreviewSource(
    value: string,
    memberMediaUrlPattern?: string,
): string | undefined {
    if (/^(https?:\/\/|\/|blob:|data:image\/)/i.test(value)) return value;
    if (!value || !memberMediaUrlPattern?.includes("__KERN_MEDIA_PATH__"))
        return undefined;
    if (/[\u0000-\u001f\\]/.test(value)) return undefined;
    const segments = value.split("/");
    if (segments.some((segment) => segment === "." || segment === ".."))
        return undefined;
    return memberMediaUrlPattern.replace(
        "__KERN_MEDIA_PATH__",
        segments.map((segment) => encodeURIComponent(segment)).join("/"),
    );
}
