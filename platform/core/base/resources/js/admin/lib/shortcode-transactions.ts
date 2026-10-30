import type { Node as ProseMirrorNode } from "@tiptap/pm/model";
import {
    NodeSelection,
    TextSelection,
    type EditorState,
    type Transaction,
} from "@tiptap/pm/state";
import { shortcodeId, updatedBlockAttributes } from "./shortcode-document";

export function findShortcodeBlock(doc: ProseMirrorNode, id: string) {
    let found: { node: ProseMirrorNode; pos: number; index: number } | null =
        null;
    doc.forEach((node, pos, index) => {
        if (node.type.name === "shortcodeBlock" && node.attrs.id === id)
            found = { node, pos, index };
    });
    return found as {
        node: ProseMirrorNode;
        pos: number;
        index: number;
    } | null;
}

export type ShortcodeBlockAction =
    "duplicate" | "delete" | "up" | "down" | "before" | "after";

export function updateShortcodeBlock(
    state: EditorState,
    id: string,
    code: string,
) {
    const target = findShortcodeBlock(state.doc, id);
    return target
        ? state.tr.setNodeMarkup(
              target.pos,
              undefined,
              updatedBlockAttributes(target.node, code),
          )
        : null;
}

export function shortcodeBlockTransaction(
    state: EditorState,
    id: string,
    action: ShortcodeBlockAction,
): Transaction | null {
    const target = findShortcodeBlock(state.doc, id);
    if (!target) return null;
    const { node, pos, index } = target;
    const tr = state.tr;
    if (action === "delete") return tr.delete(pos, pos + node.nodeSize);
    if (action === "duplicate") {
        tr.insert(
            pos + node.nodeSize,
            node.type.create({ ...node.attrs, id: shortcodeId() }),
        );
        return tr.setSelection(
            NodeSelection.create(tr.doc, pos + node.nodeSize),
        );
    }
    if (action === "before" || action === "after") {
        const at = action === "before" ? pos : pos + node.nodeSize;
        tr.insert(at, state.schema.nodes.paragraph.create());
        return tr.setSelection(TextSelection.create(tr.doc, at + 1));
    }
    const neighborIndex = index + (action === "up" ? -1 : 1);
    if (neighborIndex < 0 || neighborIndex >= state.doc.childCount) return null;
    const neighbor = state.doc.child(neighborIndex);
    const from = action === "up" ? pos - neighbor.nodeSize : pos;
    tr.replaceWith(
        from,
        from + neighbor.nodeSize + node.nodeSize,
        action === "up" ? [node, neighbor] : [neighbor, node],
    );
    return tr.setSelection(
        NodeSelection.create(
            tr.doc,
            action === "up" ? from : from + neighbor.nodeSize,
        ),
    );
}
