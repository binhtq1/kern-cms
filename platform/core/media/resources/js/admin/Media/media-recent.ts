export function rememberMedia(current: unknown, id?: unknown): string[] {
    const normalize = (value: unknown): string | null =>
        (typeof value === "string" &&
            /^\d+$/.test(value) &&
            Number(value) > 0) ||
        (typeof value === "number" && Number.isSafeInteger(value) && value > 0)
            ? String(value)
            : null;
    const next = normalize(id);
    const previous = Array.isArray(current)
        ? current
              .map(normalize)
              .filter((value): value is string => value !== null)
        : [];
    return [...new Set(next ? [next, ...previous] : previous)].slice(0, 40);
}
