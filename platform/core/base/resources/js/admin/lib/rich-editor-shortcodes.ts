import type { Option } from "../types";
import { attributeTokens } from "./shortcode-parser.ts";
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
    for (const token of attributeTokens(code)) {
        const decoder = ownerDocument.createElement("textarea");
        decoder.innerHTML = token.value;
        attributes[token.key.toLowerCase()] = decoder.value;
    }
    return attributes;
}
