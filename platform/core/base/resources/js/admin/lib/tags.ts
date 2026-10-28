export function parseTags(value: string): string[] {
    const seen = new Set<string>();
    return value
        .split(",")
        .map((tag) => tag.trim())
        .filter((tag) => {
            const key = tag.toLocaleLowerCase();
            if (!tag || seen.has(key)) return false;
            seen.add(key);
            return true;
        });
}

export function tagSuggestions(value: string, options: string[]): string[] {
    const parts = value.split(",");
    const query = (parts.pop() ?? "").trim().toLocaleLowerCase();
    const selected = new Set(
        parseTags(parts.join(",")).map((tag) => tag.toLocaleLowerCase()),
    );
    return parseTags(options.join(","))
        .filter(
            (tag) =>
                !selected.has(tag.toLocaleLowerCase()) &&
                tag.toLocaleLowerCase().includes(query),
        )
        .slice(0, 20);
}

export function completeTag(value: string, tag: string): string {
    const parts = value.split(",");
    parts.pop();
    const tags = parseTags([...parts, tag].join(","));
    return tags.length ? tags.join(", ") + ", " : "";
}
