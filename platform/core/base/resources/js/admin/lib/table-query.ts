const queryKeys = new Set([
    "search",
    "status",
    "template",
    "sort",
    "direction",
    "page",
    "per_page",
    "ref_lang",
    "advanced",
]);

/** Carry applied server filters, never mutation payload or an arbitrary return URL. */
export function tableActionUrl(url: string, filters: object): string {
    const absolute = /^[a-z][a-z\d+.-]*:/i.test(url);
    const target = new URL(url, "https://admin.invalid");
    for (const [key, value] of Object.entries(filters)) {
        if (!queryKeys.has(key)) continue;
        for (const existing of [...target.searchParams.keys()]) {
            if (existing === key || existing.startsWith(`${key}[`))
                target.searchParams.delete(existing);
        }
        if (key === "advanced") {
            if (!Array.isArray(value)) continue;
            value.forEach((filter, index) => {
                if (!filter || typeof filter !== "object") return;
                for (const field of ["field", "operator", "value"]) {
                    if (typeof filter[field] === "string")
                        target.searchParams.set(
                            `advanced[${index}][${field}]`,
                            filter[field],
                        );
                }
            });
        } else if (
            (typeof value === "string" || typeof value === "number") &&
            value !== ""
        ) {
            target.searchParams.set(key, String(value));
        }
    }
    return absolute
        ? target.href
        : `${target.pathname}${target.search}${target.hash}`;
}
