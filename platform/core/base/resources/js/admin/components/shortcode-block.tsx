import { Node } from "@tiptap/core";
import { Plugin } from "@tiptap/pm/state";
import {
    NodeViewWrapper,
    ReactNodeViewRenderer,
    useEditorState,
    type NodeViewProps,
} from "@tiptap/react";
import {
    ArrowDown,
    ArrowUp,
    Copy,
    GripVertical,
    Settings2,
    Trash2,
    Type,
} from "lucide-react";
import { blockCode, shortcodeId } from "../lib/shortcode-document";
import { readAttributes, type Shortcode } from "../lib/rich-editor-shortcodes";
import { shortcodeBlockTransaction } from "../lib/shortcode-transactions";
import { useAdminTranslation } from "../lib/translate";
import { Button } from "./ui/button";
import { DropdownMenu, DropdownMenuItem } from "./ui/dropdown-menu";

export type ShortcodeBlockOptions = {
    definitions: () => Shortcode[];
    configure: (id: string, code: string, element: HTMLElement) => void;
};

function ShortcodeBlockView({
    node,
    editor,
    extension,
    selected,
}: NodeViewProps) {
    const t = useAdminTranslation();
    const options = extension.options as ShortcodeBlockOptions;
    const definition = options
        .definitions()
        .find((item) => item.key === node.attrs.key);
    const code = blockCode(node);
    const attributes = readAttributes(code);
    const summary = Object.entries(attributes)
        .slice(0, 3)
        .map(([key, value]) => `${key}: ${value}`)
        .join(" · ");
    const act = (action: Parameters<typeof shortcodeBlockTransaction>[2]) => {
        const transaction = shortcodeBlockTransaction(
            editor.state,
            node.attrs.id,
            action,
        );
        if (transaction) {
            editor.view.dispatch(transaction.scrollIntoView());
            editor.view.focus();
        }
    };
    const currentDoc = useEditorState({
        editor,
        selector: ({ editor }) => editor.state.doc,
    });
    let pos = -1;
    currentDoc.forEach((item, _offset, index) => {
        if (item.attrs.id === node.attrs.id) pos = index;
    });
    return (
        <NodeViewWrapper
            contentEditable={false}
            role="group"
            aria-label={definition?.name ?? node.attrs.key}
            data-shortcode-block={node.attrs.key}
            className={`my-3 overflow-hidden rounded-lg border bg-card text-card-foreground ${selected ? "ring-2 ring-ring" : ""}`}
        >
            <div className="flex items-start gap-2 p-3">
                <span
                    data-drag-handle
                    className="mt-1 cursor-grab rounded p-1 text-muted-foreground"
                    title={t("Drag to reorder")}
                    aria-hidden="true"
                >
                    <GripVertical size={18} />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="text-sm font-semibold">
                        {definition?.name ?? node.attrs.key}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {summary || t("Shortcode")}
                    </div>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={!definition}
                    onClick={(event) =>
                        options.configure(
                            node.attrs.id,
                            code,
                            event.currentTarget,
                        )
                    }
                >
                    <Settings2 size={14} />
                    {t("Configure")}
                </Button>
                <DropdownMenu label={t("Block actions")}>
                    <DropdownMenuItem
                        disabled={pos <= 0}
                        onSelect={() => act("up")}
                    >
                        <ArrowUp />
                        {t("Move up")}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={pos < 0 || pos >= currentDoc.childCount - 1}
                        onSelect={() => act("down")}
                    >
                        <ArrowDown />
                        {t("Move down")}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => act("duplicate")}>
                        <Copy />
                        {t("Duplicate")}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => act("before")}>
                        <Type />
                        {t("Add text before")}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => act("after")}>
                        <Type />
                        {t("Add text after")}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        destructive
                        onSelect={() => act("delete")}
                    >
                        <Trash2 />
                        {t("Delete")}
                    </DropdownMenuItem>
                </DropdownMenu>
            </div>
            <details className="border-t px-3 py-2 text-xs text-muted-foreground">
                <summary className="cursor-pointer focus-visible:outline-ring">
                    {t("Shortcode source")}
                </summary>
                <pre className="mt-2 text-xs text-foreground">{code}</pre>
            </details>
        </NodeViewWrapper>
    );
}

// The separate group restricts cards to the document root. A drag into a list,
// table or paragraph cannot create a persisted editor-only wrapper.
export const ShortcodeDocument = Node.create({
    name: "doc",
    topNode: true,
    content: "(block | shortcodeBlock)+",
});

export const ShortcodeBlock = Node.create<ShortcodeBlockOptions>({
    name: "shortcodeBlock",
    group: "shortcodeBlock",
    atom: true,
    draggable: true,
    selectable: true,
    addOptions: () => ({ definitions: () => [], configure: () => {} }),
    addAttributes: () =>
        Object.fromEntries(
            ["id", "key", "rawHtml", "codeFrom", "codeTo"].map((name) => [
                name,
                { default: null, rendered: false },
            ]),
        ),
    // Only our codec creates these nodes; never interpret user HTML as a card.
    parseHTML: () => [],
    renderHTML: ({ node }) => ["p", {}, blockCode(node)],
    renderText: ({ node }) => blockCode(node),
    addNodeView: () => ReactNodeViewRenderer(ShortcodeBlockView),
    addProseMirrorPlugins() {
        return [
            new Plugin({
                appendTransaction(transactions, _old, state) {
                    if (
                        !transactions.some(
                            (transaction) => transaction.docChanged,
                        )
                    )
                        return null;
                    const ids = new Set<string>();
                    const tr = state.tr;
                    state.doc.forEach((node, pos) => {
                        if (node.type.name !== "shortcodeBlock") return;
                        if (!node.attrs.id || ids.has(node.attrs.id))
                            tr.setNodeMarkup(pos, undefined, {
                                ...node.attrs,
                                id: shortcodeId(),
                            });
                        ids.add(tr.doc.nodeAt(pos)!.attrs.id);
                    });
                    return tr.docChanged ? tr : null;
                },
            }),
        ];
    },
});
