import test from "node:test";
import assert from "node:assert/strict";
import { createServer } from "vite";
import { resolve } from "node:path";

test("extension factory preserves installed Tiptap order and legacy compatibility configuration", async () => {
    const server = await createServer({
        configFile: false,
        resolve: {
            alias: {
                "@admin": resolve("platform/core/base/resources/js/admin"),
            },
            dedupe: ["@tiptap/core", "@tiptap/pm"],
        },
        logLevel: "silent",
        server: { middlewareMode: true, watch: null },
        appType: "custom",
    });
    try {
        const { createRichEditorExtensions } = await server.ssrLoadModule(
            "/platform/core/base/resources/js/admin/components/rich-editor-extension-config.ts",
        );
        const extensions = createRichEditorExtensions();
        assert.deepEqual(
            extensions.map((extension) => extension.name),
            [
                "starterKit",
                "image",
                "inlineImage",
                "blockPresentation",
                "htmlEmbed",
                "searchHighlights",
                "tableKit",
                "legacyTableAttributes",
                "textStyleKit",
                "textAlign",
                "youtube",
            ],
        );
        assert.deepEqual(extensions[0].options, {
            link: { openOnClick: false },
            heading: { levels: [1, 2, 3, 4, 5, 6] },
        });
        assert.equal(extensions[6].options.table.View.name, "LegacyTableView");
        assert.ok(extensions[7].config.addGlobalAttributes);
        assert.deepEqual(extensions[9].options.types, ["heading", "paragraph"]);
        assert.equal(extensions[10].options.nocookie, true);
    } finally {
        await server.close();
    }
});
