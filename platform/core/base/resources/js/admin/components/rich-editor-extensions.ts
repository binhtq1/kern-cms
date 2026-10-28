import {
    Extension,
    Node,
    mergeAttributes,
    type Attributes,
} from "@tiptap/core";
import Image from "@tiptap/extension-image";
import { Plugin, PluginKey } from "@tiptap/pm/state";
import { Decoration, DecorationSet } from "@tiptap/pm/view";
import { textMatches } from "./rich-editor-tools";

export const SearchHighlights = Extension.create({
    name: "searchHighlights",
    addProseMirrorPlugins() {
        const key = new PluginKey<{
            find: string;
            caseSensitive: boolean;
            decorations: DecorationSet;
        }>("adminFind");
        return [
            new Plugin({
                key,
                state: {
                    init: () => ({
                        find: "",
                        caseSensitive: false,
                        decorations: DecorationSet.empty,
                    }),
                    apply: (transaction, previous) => {
                        const query =
                            transaction.getMeta("adminFind") ?? previous;
                        if (!transaction.docChanged && query === previous)
                            return previous;
                        const decorations: Decoration[] = [];
                        transaction.doc.descendants((node, position) => {
                            if (!node.isTextblock) return;
                            for (const index of textMatches(
                                node.textBetween(
                                    0,
                                    node.content.size,
                                    "\n",
                                    "\uFFFC",
                                ),
                                query.find,
                                query.caseSensitive,
                            ))
                                decorations.push(
                                    Decoration.inline(
                                        position + 1 + index,
                                        position +
                                            1 +
                                            index +
                                            query.find.length,
                                        { class: "admin-editor-match" },
                                    ),
                                );
                            return false;
                        });
                        return {
                            find: query.find,
                            caseSensitive: query.caseSensitive,
                            decorations: DecorationSet.create(
                                transaction.doc,
                                decorations,
                            ),
                        };
                    },
                },
                props: {
                    decorations: (state) => key.getState(state)?.decorations,
                },
            }),
        ];
    },
});

export const BlockPresentation = Extension.create({
    name: "blockPresentation",
    addGlobalAttributes() {
        return [
            {
                types: ["paragraph", "heading"],
                attributes: {
                    direction: {
                        default: null,
                        parseHTML: (element) => element.getAttribute("dir"),
                        renderHTML: (attrs) =>
                            attrs.direction ? { dir: attrs.direction } : {},
                    },
                    indent: {
                        default: 0,
                        parseHTML: (element) =>
                            Math.max(
                                0,
                                Math.min(
                                    8,
                                    (parseFloat(element.style.marginLeft) ||
                                        0) / 40,
                                ),
                            ),
                        renderHTML: (attrs) =>
                            attrs.indent
                                ? {
                                      style: `margin-left: ${attrs.indent * 40}px`,
                                  }
                                : {},
                    },
                },
            },
        ];
    },
});

export const CaptionedImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            caption: { default: "", rendered: false },
            imageStyle: {
                default: null,
                parseHTML: (element) => element.getAttribute("style"),
                renderHTML: (attrs) =>
                    attrs.imageStyle ? { style: attrs.imageStyle } : {},
            },
            layout: {
                default: "",
                parseHTML: (element) =>
                    (element.tagName === "FIGURE"
                        ? element.querySelector("img")?.style.cssFloat
                        : element.style.cssFloat) || null,
                renderHTML: (attrs) =>
                    attrs.layout
                        ? {
                              style: `float: ${attrs.layout}; margin: 0 1rem 1rem;`,
                          }
                        : {},
            },
        };
    },
    parseHTML() {
        return [
            {
                tag: "figure",
                priority: 100,
                getAttrs: (element) => {
                    const image = element.querySelector("img");
                    if (!image || element.children.length > 2) return false;
                    return {
                        src: image.getAttribute("src"),
                        alt: image.getAttribute("alt"),
                        title: image.getAttribute("title"),
                        width: image.getAttribute("width"),
                        height: image.getAttribute("height"),
                        caption:
                            element.querySelector("figcaption")?.textContent ??
                            "",
                        layout: image.style.cssFloat || "",
                        imageStyle: image.getAttribute("style"),
                    };
                },
            },
            ...(this.parent?.() ?? []),
        ];
    },
    renderHTML({ node, HTMLAttributes }) {
        const image = [
            "img",
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
        ] as const;
        return node.attrs.caption
            ? ["figure", {}, image, ["figcaption", {}, node.attrs.caption]]
            : image;
    },
});

/** Preserve legacy images authored inside paragraphs without changing block images or figures. */
export const InlineImage = CaptionedImage.extend({
    name: "inlineImage",
    addAttributes() {
        const attributes: Attributes = this.parent?.() ?? {};
        return {
            ...attributes,
            layout: { ...attributes.layout, renderHTML: () => ({}) },
        };
    },
    parseHTML() {
        return [
            {
                tag: 'img[src]:not([src^="data:"])',
                priority: 110,
                getAttrs: (element) =>
                    element.closest("p,h1,h2,h3,h4,h5,h6") ? {} : false,
            },
        ];
    },
    renderHTML({ HTMLAttributes }) {
        return [
            "img",
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
        ];
    },
    addCommands() {
        return {};
    },
    addInputRules() {
        return [];
    },
}).configure({ inline: true });

/** Keep arbitrary authored HTML as an atomic block; editor previews never execute its scripts. */
export const HtmlEmbed = Node.create({
    name: "htmlEmbed",
    group: "block",
    atom: true,
    draggable: true,
    addAttributes() {
        return { html: { default: "", rendered: false } };
    },
    parseHTML() {
        return [
            {
                tag: "div[data-admin-html-embed]",
                getAttrs: (element) => ({ html: element.innerHTML }),
            },
        ];
    },
    renderHTML({ node }) {
        const wrapper = document.createElement("div");
        wrapper.setAttribute("data-admin-html-embed", "");
        wrapper.innerHTML = node.attrs.html;
        return { dom: wrapper };
    },
    addNodeView() {
        return ({ node, editor, getPos }) => {
            const dom = document.createElement("div");
            dom.className = "rounded border bg-muted/20 p-2 my-3";
            dom.contentEditable = "false";
            const select = document.createElement("button");
            select.type = "button";
            select.textContent = "HTML embed";
            select.className =
                "text-xs font-medium text-muted-foreground mb-2 rounded focus-visible:ring-2 focus-visible:ring-ring";
            select.addEventListener("click", () => {
                const position = getPos();
                if (position !== undefined)
                    editor.chain().focus().setNodeSelection(position).run();
            });
            const frame = document.createElement("iframe");
            frame.setAttribute("sandbox", "");
            frame.title = "HTML embed preview";
            frame.className = "w-full min-h-48 border-0";
            frame.srcdoc = node.attrs.html;
            dom.append(select, frame);
            return {
                dom,
                update: (next) => {
                    if (next.type.name !== "htmlEmbed") return false;
                    frame.srcdoc = next.attrs.html;
                    return true;
                },
            };
        };
    },
});
