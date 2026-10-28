export function contactLink(kind: string, value: string): string | null {
    const text = value.trim();
    if (!text || /[\u0000-\u001f\u007f]/.test(text)) return null;
    if (kind === "email" && /^[^@\s<>]+@[^@\s<>]+\.[^@\s<>]+$/.test(text))
        return `mailto:${encodeURIComponent(text).replace(/%40/g, "@")}`;
    if (kind === "phone" && /^[+()\d.\-\s]+$/.test(text) && /\d/.test(text))
        return `tel:${encodeURIComponent(text)}`;
    return null;
}
