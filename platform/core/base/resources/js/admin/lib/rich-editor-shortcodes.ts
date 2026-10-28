import type { Option } from "../types";
type ShortcodeField = {
    name: string;
    type: string;
    label: string;
    description?: string;
    value?: string | number | boolean | string[];
    choices?: Option[];
    multiple?: boolean;
};
export type Shortcode = {
    key: string;
    name: string;
    code: string;
    fields?: ShortcodeField[];
    previewUrl?: string;
    description?: string;
    previewImage?: string;
};
export const escapeAttribute = (value: string) =>
    value
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/\[/g, "&#91;")
        .replace(/\]/g, "&#93;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
export function readAttributes(
    code: string,
    ownerDocument: Pick<Document, "createElement"> = document,
): Record<string, string> {
    const attributes: Record<string, string> = {};
    for (const match of code
        .split("]")[0]
        .matchAll(/([\w-]+)=(?:"([^"]*)"|'([^']*)')/g)) {
        const decoder = ownerDocument.createElement("textarea");
        decoder.innerHTML = match[2] ?? match[3] ?? "";
        attributes[match[1]] = decoder.value;
    }
    return attributes;
}
