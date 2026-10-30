import {
    DOMParser,
    DOMSerializer,
    Fragment,
    type Node as ProseMirrorNode,
    type Schema,
} from "@tiptap/pm/model";
import { visualEditingIssue } from "../components/rich-editor-safety";
import { delimiterEnd, findShortcodes } from "./shortcode-parser";
import { createUuid } from "./uuid";

export const shortcodeId = createUuid;

export function shortcodeBlockAttributes(
    code: string,
    rawHtml = code,
    from = rawHtml.indexOf(code),
) {
    return {
        id: shortcodeId(),
        key: findShortcodes(code)[0]?.key ?? "",
        rawHtml,
        codeFrom: from,
        codeTo: from + code.length,
    };
}

export function blockCode(node: ProseMirrorNode) {
    return String(node.attrs.rawHtml).slice(
        node.attrs.codeFrom,
        node.attrs.codeTo,
    );
}

export function updatedBlockAttributes(node: ProseMirrorNode, code: string) {
    return {
        ...node.attrs,
        rawHtml:
            node.attrs.rawHtml.slice(0, node.attrs.codeFrom) +
            code +
            node.attrs.rawHtml.slice(node.attrs.codeTo),
        codeTo: node.attrs.codeFrom + code.length,
    };
}

// Split at root boundaries before parsing HTML. Shortcode bodies may themselves
// contain HTML and must remain opaque to the editor's HTML parser.
function rootFragments(source: string) {
    const occurrences = new Map(
        findShortcodes(source).map((item) => [item.from, item]),
    );
    const fragments: string[] = [];
    const stack: string[] = [];
    let start = 0;
    const push = (end: number) => {
        if (source.slice(start, end).trim()) {
            fragments.push(source.slice(start, end));
            start = end;
        }
    };
    for (let index = 0; index < source.length;) {
        const shortcode = occurrences.get(index);
        if (shortcode) {
            index = shortcode.to;
            if (!stack.length) push(index);
            continue;
        }
        if (source.startsWith("<!--", index)) {
            const end = source.indexOf("-->", index + 4);
            index = end < 0 ? source.length : end + 3;
            if (!stack.length) push(index);
            continue;
        }
        const tag = source.slice(index).match(/^<(\/?)([a-z][\w-]*)\b/i);
        if (tag) {
            if (!stack.length && source.slice(start, index).trim()) push(index);
            const end = delimiterEnd(source, index + tag[0].length, ">");
            const name = tag[2].toLowerCase();
            if (tag[1]) {
                if (stack.at(-1) !== name) return [source];
                stack.pop();
            } else if (
                !/^(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)$/.test(
                    name,
                ) &&
                !source.slice(index, end).endsWith("/>")
            )
                stack.push(name);
            index = end;
            if (!stack.length) push(index);
        } else index++;
    }
    if (stack.length) return [source];
    if (source.slice(start).trim()) fragments.push(source.slice(start));
    else if (fragments.length)
        fragments[fragments.length - 1] += source.slice(start);
    return fragments;
}

export function createShortcodeDocumentCodec(
    schema: Schema,
    ownerDocument: Document = document,
) {
    let original = "";
    let initial: ProseMirrorNode | null = null;
    const preserved = new WeakMap<ProseMirrorNode, string>();
    const parser = DOMParser.fromSchema(schema);
    const serializer = DOMSerializer.fromSchema(schema);
    const serializeNode = (node: ProseMirrorNode) => {
        const container = ownerDocument.createElement("div");
        container.appendChild(
            serializer.serializeNode(node, { document: ownerDocument }),
        );
        return container.innerHTML;
    };
    const nodeHtml = (node: ProseMirrorNode) =>
        node.type.name === "shortcodeBlock"
            ? (node.attrs.rawHtml as string)
            : (preserved.get(node) ?? serializeNode(node));
    return {
        parse(html: string) {
            original = html;
            let issue: string | null = null;
            const nodes: ProseMirrorNode[] = [];
            for (const raw of rootFragments(html)) {
                const matches = findShortcodes(raw);
                const only = matches.length === 1 ? matches[0] : null;
                const before = only ? raw.slice(0, only.from) : "";
                const after = only ? raw.slice(only.to) : "";
                const standalone =
                    only &&
                    ((!before.trim() && !after.trim()) ||
                        (/^\s*<p(?:\s[^>]*)?>\s*$/i.test(before) &&
                            /^\s*<\/p>\s*$/i.test(after)));
                if (standalone) {
                    nodes.push(
                        schema.nodes.shortcodeBlock.create(
                            shortcodeBlockAttributes(only.code, raw, only.from),
                        ),
                    );
                    continue;
                }
                const container = ownerDocument.createElement("div");
                container.innerHTML = raw;
                const doc = parser.parse(container);
                const normalized = ownerDocument.createElement("div");
                normalized.appendChild(
                    serializer.serializeFragment(doc.content, {
                        document: ownerDocument,
                    }),
                );
                if (
                    JSON.stringify(matches.map((item) => item.code)) !==
                    JSON.stringify(
                        findShortcodes(normalized.innerHTML).map(
                            (item) => item.code,
                        ),
                    )
                )
                    issue ??= "shortcode source";
                issue ??= visualEditingIssue(raw, normalized.innerHTML);
                doc.forEach((node) => nodes.push(node));
                if (doc.childCount === 1) preserved.set(doc.firstChild!, raw);
            }
            initial = schema.topNodeType.createAndFill(
                null,
                Fragment.fromArray(nodes),
            )!;
            return { doc: initial, issue };
        },
        serialize(doc: ProseMirrorNode) {
            if (initial && doc.eq(initial)) return original;
            let html = "";
            doc.forEach((node) => {
                html += nodeHtml(node);
            });
            return html;
        },
        blockAt(doc: ProseMirrorNode, from: number) {
            let offset = 0;
            let id: string | null = null;
            doc.forEach((node) => {
                if (
                    node.type.name === "shortcodeBlock" &&
                    offset + node.attrs.codeFrom === from
                )
                    id = node.attrs.id;
                offset += nodeHtml(node).length;
            });
            return id;
        },
    };
}
