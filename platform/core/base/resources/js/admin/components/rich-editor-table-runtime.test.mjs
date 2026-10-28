import test from "node:test";
import assert from "node:assert/strict";
import { createRequire } from "node:module";
import { pathToFileURL } from "node:url";
import { resolveConfig, createIdResolver, BuildEnvironment } from "vite";
import { getSchema } from "@tiptap/core";
import StarterKit from "@tiptap/starter-kit";
import { TableKit } from "@tiptap/extension-table";

test("installed table cell selection, heading and merge/split compose decorations through the actual admin resolver", async () => {
    const config = await resolveConfig(
        { configFile: "vite.config.ts", logLevel: "silent" },
        "build",
    );
    const resolve = createIdResolver(config);
    const environment = new BuildEnvironment("client", config);
    const editor = `${config.root}/platform/core/base/resources/js/admin/components/rich-editor.tsx`;
    const table = createRequire(import.meta.url).resolve(
        "@tiptap/extension-table",
    );
    const load = async (id, importer) =>
        import(pathToFileURL(await resolve(environment, id, importer)).href);
    const { Decoration, DecorationSet } = await load("@tiptap/pm/view", editor);
    const { EditorState } = await load("@tiptap/pm/state", editor);
    const { setBlockType } = await load("@tiptap/pm/commands", editor);
    const { CellSelection, tableEditing, mergeCells, splitCell } = await load(
        "@tiptap/pm/tables",
        table,
    );
    const schema = getSchema([StarterKit, TableKit]);
    const cell = (text) => ({
        type: "tableCell",
        content: [{ type: "paragraph", content: [{ type: "text", text }] }],
    });
    const doc = schema.nodeFromJSON({
        type: "doc",
        content: [
            {
                type: "table",
                content: [
                    { type: "tableRow", content: [cell("A"), cell("B")] },
                    { type: "tableRow", content: [cell("C"), cell("D")] },
                ],
            },
        ],
    });
    const cells = [];
    doc.descendants((node, position) => {
        if (node.type.name === "tableCell") cells.push(position);
    });
    const plugin = tableEditing();
    let state = EditorState.create({
        schema,
        doc,
        selection: new CellSelection(
            doc.resolve(cells[0]),
            doc.resolve(cells[1]),
        ),
        plugins: [plugin],
    });
    const dispatch = (transaction) => {
        state = state.applyTransaction(transaction).state;
    };

    // Obtain the installed view's decoration compositor through its public child-decoration API.
    const tableNode = doc.firstChild;
    const child = DecorationSet.create(tableNode, [
        Decoration.node(0, tableNode.firstChild.nodeSize, { class: "row" }),
    ]);
    const group = new DecorationSet(
        [Decoration.inline(0, tableNode.content.size + 2, { class: "range" })],
        [0, tableNode.nodeSize, child],
    ).forChild(0, tableNode);
    const compositor = group.constructor;
    function drawDecorations() {
        const selected = plugin.props.decorations(state);
        const highlights = DecorationSet.create(state.doc, [
            Decoration.inline(4, 5, { class: "highlight" }),
        ]);
        assert.doesNotThrow(() => {
            const combined = compositor.from([highlights, selected]);
            combined.locals(state.doc);
            combined
                .forChild(0, state.doc.firstChild)
                .locals(state.doc.firstChild);
            combined.eq(compositor.from([highlights, selected]));
        });
    }
    drawDecorations();
    assert.equal(
        setBlockType(schema.nodes.heading, { level: 2 })(state, dispatch),
        true,
    );
    assert.equal(
        state.doc.firstChild.firstChild.firstChild.firstChild.type.name,
        "heading",
    );
    drawDecorations();
    assert.equal(mergeCells(state, dispatch), true);
    assert.equal(state.doc.firstChild.firstChild.firstChild.attrs.colspan, 2);
    drawDecorations();
    assert.equal(splitCell(state, dispatch), true);
    assert.equal(state.doc.firstChild.firstChild.childCount, 2);
    assert.equal(state.doc.textContent, "ABCD");
    drawDecorations();
});
