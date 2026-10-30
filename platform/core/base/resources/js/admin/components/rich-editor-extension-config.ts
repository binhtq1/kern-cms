import { Extension } from "@tiptap/core";
import { TableKit } from "@tiptap/extension-table";
import TextAlign from "@tiptap/extension-text-align";
import { TextStyleKit } from "@tiptap/extension-text-style";
import Youtube from "@tiptap/extension-youtube";
import StarterKit from "@tiptap/starter-kit";
import {
    BlockPresentation,
    CaptionedImage,
    HtmlEmbed,
    InlineImage,
    SearchHighlights,
} from "./rich-editor-extensions";
import { LegacyTableView } from "./rich-editor-table";
import {
    ShortcodeBlock,
    ShortcodeDocument,
    type ShortcodeBlockOptions,
} from "./shortcode-block";

const LegacyTableAttributes = Extension.create({
    name: "legacyTableAttributes",
    addGlobalAttributes() {
        return [
            {
                types: ["table", "tableRow", "tableCell", "tableHeader"],
                attributes: Object.fromEntries(
                    [
                        "style",
                        "width",
                        "height",
                        "bgcolor",
                        "align",
                        "valign",
                        "border",
                        "cellpadding",
                        "cellspacing",
                    ].map((name) => [
                        `legacy_${name}`,
                        {
                            default: null,
                            parseHTML: (element: HTMLElement) =>
                                element.getAttribute(name),
                            renderHTML: (
                                attributes: Record<string, unknown>,
                            ) =>
                                attributes[`legacy_${name}`]
                                    ? { [name]: attributes[`legacy_${name}`] }
                                    : {},
                        },
                    ]),
                ),
            },
        ];
    },
});

export function createRichEditorExtensions(shortcodes?: ShortcodeBlockOptions) {
    return [
        StarterKit.configure({
            ...(shortcodes ? { document: false as const } : {}),
            link: { openOnClick: false },
            heading: { levels: [1, 2, 3, 4, 5, 6] },
        }),
        CaptionedImage,
        InlineImage,
        BlockPresentation,
        HtmlEmbed,
        SearchHighlights,
        TableKit.configure({ table: { View: LegacyTableView } }),
        LegacyTableAttributes,
        TextStyleKit,
        TextAlign.configure({ types: ["heading", "paragraph"] }),
        Youtube.configure({ nocookie: true }),
        ...(shortcodes
            ? [ShortcodeDocument, ShortcodeBlock.configure(shortcodes)]
            : []),
    ];
}
