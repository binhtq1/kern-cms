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
