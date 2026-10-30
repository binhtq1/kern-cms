import test from "node:test";
import assert from "node:assert/strict";
import { resolve } from "node:path";
import { createServer } from "vite";
import { Element } from "domhandler";
import { getSchema } from "@tiptap/core";
import { EditorState, NodeSelection } from "@tiptap/pm/state";
import { history, undo, redo, closeHistory } from "@tiptap/pm/history";
import { domDocument } from "./rich-editor-test-dom.mjs";

test("shortcode blocks preserve legacy source and target immutable node IDs across edits, history and source roundtrips", async (t) => {
    const server = await createServer({
        configFile: false,
        resolve: {
            alias: {
                "@admin": resolve("platform/core/base/resources/js/admin"),
            },
            dedupe: ["@tiptap/core", "@tiptap/pm"],
        },
        server: { middlewareMode: true, watch: null },
        appType: "custom",
        logLevel: "silent",
    });
    const previous = {
        document: globalThis.document,
        HTMLElement: globalThis.HTMLElement,
        Node: globalThis.Node,
    };
    try {
        const { createRichEditorExtensions } = await server.ssrLoadModule(
            "/platform/core/base/resources/js/admin/components/rich-editor-extension-config.ts",
        );
        const { createShortcodeDocumentCodec, blockCode } =
            await server.ssrLoadModule(
                "/platform/core/base/resources/js/admin/lib/shortcode-document.ts",
            );
        const { updateShortcodeBlock, shortcodeBlockTransaction } =
            await server.ssrLoadModule(
                "/platform/core/base/resources/js/admin/lib/shortcode-transactions.ts",
            );
        globalThis.document = domDocument();
        globalThis.HTMLElement = Element;
        globalThis.Node = { TEXT_NODE: 3 };
        const extensions = createRichEditorExtensions({
            definitions: () => [],
            configure: () => {},
        });
        const schema = getSchema(extensions);
        const blockExtension = extensions.find(
            (extension) => extension.name === "shortcodeBlock",
        );
        const code =
            "[hero title='Original' custom=keep][hero]Nested[/hero][/hero]";
        const source = `\n<p style="text-align: center">Intro &amp; text</p>\n<p class='legacy'>${code}</p>\n${code}\n<p>End</p>\n`;
        const codec = createShortcodeDocumentCodec(schema);
        const imported = codec.parse(source);
        assert.equal(imported.issue, null);
        let state = EditorState.create({
            schema,
            doc: imported.doc,
            plugins: [history()],
        });
        const apply = (tr) => {
            assert.ok(tr);
            state = state.apply(tr);
        };
        const blocks = () => {
            const result = [];
            state.doc.forEach((node) => {
                if (node.type.name === "shortcodeBlock") result.push(node);
            });
            return result;
        };
        const first = blocks()[0].attrs.id;
        const second = blocks()[1].attrs.id;
        assert.notEqual(first, second);
        assert.equal(codec.serialize(state.doc), source);
        assert.equal(
            codec.blockAt(state.doc, source.lastIndexOf(code)),
            second,
        );

        await t.test(
            "only the second identical block changes; unrelated HTML stays byte-for-byte",
            () => {
                const replacement = code.replace("Original", "Second");
                apply(updateShortcodeBlock(state, second, replacement));
                assert.equal(blockCode(blocks()[0]), code);
                assert.equal(
                    codec.serialize(state.doc),
                    source.slice(0, source.lastIndexOf(code)) +
                        replacement +
                        source.slice(source.lastIndexOf(code) + code.length),
                );
                assert.ok(undo(state, apply));
                assert.equal(codec.serialize(state.doc), source);
                assert.ok(redo(state, apply));
                assert.equal(blockCode(blocks()[1]), replacement);
            },
        );
        await t.test(
            "duplicate, move, delete and undo preserve identities and source format",
            () => {
                apply(closeHistory(state.tr));
                apply(shortcodeBlockTransaction(state, second, "duplicate"));
                const copy = blocks()[2].attrs.id;
                assert.notEqual(copy, second);
                apply(shortcodeBlockTransaction(state, copy, "up"));
                assert.equal(blocks()[1].attrs.id, copy);
                apply(updateShortcodeBlock(state, copy, "[hero]Copy[/hero]"));
                assert.equal(
                    blockCode(blocks()[2]),
                    code.replace("Original", "Second"),
                );
                apply(closeHistory(state.tr));
                apply(shortcodeBlockTransaction(state, copy, "delete"));
                assert.equal(blocks().length, 2);
                assert.equal(
                    updateShortcodeBlock(state, copy, "[hero]stale[/hero]"),
                    null,
                );
                assert.ok(undo(state, apply));
                assert.equal(blocks().length, 3);
                assert.equal(blockCode(blocks()[1]), "[hero]Copy[/hero]");
                assert.ok(redo(state, apply));
                assert.equal(blocks().length, 2);
            },
        );
        await t.test(
            "text insertion and source reload do not leak cards, IDs or markup",
            () => {
                apply(shortcodeBlockTransaction(state, second, "before"));
                apply(state.tr.insertText("Between blocks"));
                const html = codec.serialize(state.doc);
                assert.ok(html.includes("<p>Between blocks</p>"));
                assert.ok(html.includes("<p class='legacy'>"));
                assert.ok(!html.includes(first));
                assert.ok(!html.includes("data-shortcode"));
                const reloaded = createShortcodeDocumentCodec(schema);
                const next = reloaded.parse(html);
                assert.equal(next.issue, null);
                assert.equal(reloaded.serialize(next.doc), html);
                assert.equal(next.doc.childCount, state.doc.childCount);
            },
        );
        await t.test(
            "inline and unknown shortcode syntax is retained; unsupported HTML still triggers source protection",
            () => {
                for (const html of [
                    "<p>Before [hero title='x']body[/hero] after</p>",
                    "[unknown odd='value']<aside>Custom</aside>[/unknown]",
                    "<p>[hero][/hero][hero][/hero]</p>",
                ]) {
                    const next = createShortcodeDocumentCodec(schema);
                    const result = next.parse(html);
                    assert.equal(result.issue, null, html);
                    assert.equal(next.serialize(result.doc), html);
                }
                const result = createShortcodeDocumentCodec(schema).parse(
                    "<section class='custom'><p>Do not lose this</p></section>[hero][/hero]",
                );
                assert.ok(result.issue);
                assert.equal(
                    schema.nodes.blockquote.contentMatch.matchType(
                        schema.nodes.shortcodeBlock,
                    ),
                    null,
                );
                assert.equal(
                    schema.nodes.bulletList.contentMatch.matchType(
                        schema.nodes.shortcodeBlock,
                    ),
                    null,
                );
            },
        );
        await t.test(
            "a moved node remains selectable and edits resolve its current position",
            () => {
                apply(shortcodeBlockTransaction(state, second, "up"));
                assert.ok(state.selection instanceof NodeSelection);
                apply(
                    updateShortcodeBlock(state, second, "[hero]Moved[/hero]"),
                );
                assert.equal(
                    blockCode(
                        blocks().find((node) => node.attrs.id === second),
                    ),
                    "[hero]Moved[/hero]",
                );
            },
        );
        await t.test(
            "clipboard duplicates receive unique IDs before a subsequent edit",
            () => {
                const initial = codec.parse(code).doc;
                let copied = EditorState.create({
                    schema,
                    doc: initial,
                    plugins:
                        blockExtension.config.addProseMirrorPlugins.call(
                            blockExtension,
                        ),
                });
                copied = copied.applyTransaction(
                    copied.tr.insert(initial.content.size, initial.firstChild),
                ).state;
                const firstId = copied.doc.firstChild.attrs.id;
                const secondId = copied.doc.lastChild.attrs.id;
                assert.notEqual(firstId, secondId);
                copied = copied.apply(
                    updateShortcodeBlock(
                        copied,
                        secondId,
                        "[hero]Copied[/hero]",
                    ),
                );
                assert.equal(blockCode(copied.doc.firstChild), code);
                assert.equal(
                    blockCode(copied.doc.lastChild),
                    "[hero]Copied[/hero]",
                );
            },
        );
        await t.test(
            "inline entity syntax and unsupported HTML remain protected in source mode",
            () => {
                const html =
                    '<p>Before [hero title="A &quot;quote&quot;"]body[/hero] after</p>';
                const next = createShortcodeDocumentCodec(schema);
                const result = next.parse(html);
                assert.equal(result.issue, "shortcode source");
                assert.equal(next.serialize(result.doc), html);
                const sample = createShortcodeDocumentCodec(schema).parse(
                    "<pre>[hero][/hero]</pre>",
                );
                let cards = 0;
                sample.doc.descendants((node) => {
                    if (node.type.name === "shortcodeBlock") cards++;
                });
                assert.equal(cards, 0);
            },
        );
    } finally {
        Object.assign(globalThis, previous);
        await server.close();
    }
});
