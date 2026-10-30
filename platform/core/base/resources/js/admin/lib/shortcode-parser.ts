export type ShortcodeOccurrence = {
    key: string;
    from: number;
    to: number;
    openEnd: number;
    closeFrom: number;
    code: string;
    content: string;
};

// Both HTML attributes and shortcode attributes may contain a closing delimiter.
export function delimiterEnd(source: string, from: number, delimiter: string) {
    let quote = "";
    for (let index = from; index < source.length; index++) {
        const char = source[index];
        if (quote) {
            if (char === quote) quote = "";
        } else if (char === '"' || char === "'") quote = char;
        else if (char === delimiter) return index + 1;
    }
    return source.length;
}

export function findShortcodes(source: string): ShortcodeOccurrence[] {
    const found: ShortcodeOccurrence[] = [];
    const stack: { key: string; from: number; openEnd: number }[] = [];
    for (let index = 0; index < source.length;) {
        if (source.startsWith("<!--", index)) {
            const end = source.indexOf("-->", index + 4);
            index = end < 0 ? source.length : end + 3;
            continue;
        }
        if (source[index] === "<" && /^<\/?[a-z!]/i.test(source.slice(index))) {
            const tag = source.slice(index).match(/^<([a-z][\w-]*)\b/i)?.[1];
            index = delimiterEnd(source, index + 1, ">");
            if (tag && /^(script|style|pre|code|textarea)$/i.test(tag)) {
                const close = new RegExp(`</${tag}\\s*>`, "ig");
                close.lastIndex = index;
                const match = close.exec(source);
                index = match ? close.lastIndex : source.length;
            }
            continue;
        }
        if (source.startsWith("[[", index)) {
            const end = source.indexOf("]]", index + 2);
            index = end < 0 ? source.length : end + 2;
            continue;
        }
        if (source[index] !== "[") {
            index++;
            continue;
        }
        const tag = source.slice(index).match(/^\[(\/?)([\w-]+)(?=[\s\]])/);
        if (!tag) {
            index++;
            continue;
        }
        const end = delimiterEnd(source, index + tag[0].length, "]");
        if (source[end - 1] !== "]") break;
        const key = tag[2];
        if (!tag[1]) stack.push({ key, from: index, openEnd: end });
        else if (stack.at(-1)?.key === key) {
            const open = stack.pop()!;
            if (!stack.length) {
                found.push({
                    ...open,
                    to: end,
                    closeFrom: index,
                    code: source.slice(open.from, end),
                    content: source.slice(open.openEnd, index),
                });
            }
        } else stack.length = 0;
        index = end;
    }
    return found;
}

export function attributeTokens(code: string) {
    const end = delimiterEnd(code, 1, "]");
    const header = code.slice(0, end - 1);
    return Array.from(
        header.matchAll(
            /([\w-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s'"=<>`]+))/g,
        ),
        (match) => ({
            key: match[1],
            value: match[2] ?? match[3] ?? match[4],
            quote: match[2] !== undefined ? '"' : "'",
            from: match.index!,
            to: match.index! + match[0].length,
        }),
    );
}

export type ShortcodeAttributes = Record<string, string | string[]>;
const attributeValue = (value: string | string[] | undefined) =>
    Array.isArray(value) ? value.join(",") : (value ?? "");

// Patch only fields the user changed. Unknown attributes, omitted defaults,
// spacing, quoting and nested content survive an edit to another field.
export function patchShortcode(
    original: string,
    initial: ShortcodeAttributes,
    attributes: ShortcodeAttributes,
    initialContent: string,
    content: string,
    escape: (value: string) => string,
) {
    const occurrence = findShortcodes(original)[0];
    if (!occurrence || occurrence.code !== original) return original;
    const tokens = attributeTokens(original);
    const edits: { from: number; to: number; value: string }[] = [];
    let added = "";
    for (const [key, raw] of Object.entries(attributes)) {
        if (
            key === "content" ||
            attributeValue(raw) === attributeValue(initial[key])
        )
            continue;
        const value = escape(attributeValue(raw));
        const matches = tokens.filter(
            (token) => token.key.toLowerCase() === key.toLowerCase(),
        );
        if (!matches.length) added += ` ${key}="${value}"`;
        for (const token of matches) {
            edits.push({
                from: token.from,
                to: token.to,
                value: `${token.key}=${token.quote}${value.replace(/'/g, "&#39;")}${token.quote}`,
            });
        }
    }
    if (added)
        edits.push({
            from: occurrence.openEnd - 1,
            to: occurrence.openEnd - 1,
            value: added,
        });
    if (initialContent !== content)
        edits.push({
            from: occurrence.openEnd,
            to: occurrence.closeFrom,
            value: content,
        });
    return edits
        .sort((a, b) => b.from - a.from)
        .reduce(
            (code, edit) =>
                code.slice(0, edit.from) + edit.value + code.slice(edit.to),
            original,
        );
}

export function replaceShortcodeOccurrence(
    source: string,
    snapshot: string,
    occurrence: ShortcodeOccurrence,
    code: string,
) {
    if (
        source !== snapshot ||
        source.slice(occurrence.from, occurrence.to) !== occurrence.code
    )
        return null;
    return (
        source.slice(0, occurrence.from) + code + source.slice(occurrence.to)
    );
}
