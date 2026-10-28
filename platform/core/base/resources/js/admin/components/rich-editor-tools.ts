export function textMatches(
    text: string,
    needle: string,
    caseSensitive = false,
): number[] {
    if (!needle) return [];
    const search = caseSensitive ? needle : needle.toLocaleLowerCase();
    const matches: number[] = [];
    for (let index = 0; index <= text.length - needle.length; index++) {
        const candidate = text.slice(index, index + needle.length);
        if (
            (caseSensitive ? candidate : candidate.toLocaleLowerCase()) ===
            search
        ) {
            matches.push(index);
            index += needle.length - 1;
        }
    }
    return matches;
}

export function validMediaUrl(value: string): boolean {
    const source = value.trim();
    if (/[<>"\s\\]/.test(source)) return false;
    if (source.startsWith("/") && !source.startsWith("//")) return true;
    try {
        const url = new URL(source);
        return (
            ["http:", "https:"].includes(url.protocol) &&
            !url.username &&
            !url.password
        );
    } catch {
        return false;
    }
}

export function validDimension(value: string): boolean {
    return !value || /^(?:\d+(?:\.\d+)?)(?:px|%)?$/.test(value);
}

export function clampIndent(value: number): number {
    return Math.min(8, Math.max(0, value));
}
