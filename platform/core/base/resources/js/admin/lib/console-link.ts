export function safeConsoleLink(value: string | undefined): string | null {
    if (
        !value ||
        /[\u0000-\u0020\u007f]/.test(value) ||
        /^[\\/]{2}/.test(value) ||
        value.includes("\\")
    )
        return null;
    try {
        const url = new URL(value, "https://admin.invalid/");
        return ["https:", "http:"].includes(url.protocol) ? value : null;
    } catch {
        return null;
    }
}
