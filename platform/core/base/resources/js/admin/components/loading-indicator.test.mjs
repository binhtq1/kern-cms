import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { globSync } from "glob";
import ts from "typescript";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { App } from "@inertiajs/react";
import { createServer } from "vite";

test("admin pending labels are accessible names, not visible loading copy", () => {
    const violations = [];
    for (const file of globSync("platform/**/resources/js/admin/**/*.tsx")) {
        const source = ts.createSourceFile(
            file,
            readFileSync(file, "utf8"),
            ts.ScriptTarget.Latest,
            true,
            ts.ScriptKind.TSX,
        );
        const visit = (node) => {
            const isComparison =
                node.parent &&
                ts.isBinaryExpression(node.parent) &&
                [
                    ts.SyntaxKind.EqualsEqualsToken,
                    ts.SyntaxKind.EqualsEqualsEqualsToken,
                    ts.SyntaxKind.ExclamationEqualsToken,
                    ts.SyntaxKind.ExclamationEqualsEqualsToken,
                ].includes(node.parent.operatorToken.kind);
            if (
                !isComparison &&
                (ts.isStringLiteral(node) || ts.isJsxText(node)) &&
                /^(?:Loading|Searching|Saving|Exporting|Uploading|Importing|Deleting|Processing|Fetching|Checking|Connecting|Generating|Preparing|Validating|Please wait)(?:[\s.…]|$)/i.test(
                    node.text.trim(),
                )
            ) {
                let parent = node.parent;
                let inJsx = false;
                let hidden = false;
                while (parent) {
                    if (ts.isJsxAttribute(parent)) {
                        inJsx = true;
                        hidden = [
                            "label",
                            "aria-label",
                            "aria-valuetext",
                        ].includes(parent.name.getText(source));
                        break;
                    }
                    if (ts.isJsxElement(parent)) {
                        inJsx = true;
                        hidden ||=
                            parent.openingElement.attributes.properties.some(
                                (attribute) =>
                                    ts.isJsxAttribute(attribute) &&
                                    attribute.name.getText(source) ===
                                        "className" &&
                                    attribute.initializer &&
                                    ts.isStringLiteral(attribute.initializer) &&
                                    /(?:^|\s)sr-only(?:\s|$)/.test(
                                        attribute.initializer.text,
                                    ),
                            );
                    }
                    // Only inspect UI expressions; progress-stage data is intentionally retained.
                    if (ts.isStatement(parent)) break;
                    parent = parent.parent;
                }
                if (inJsx && !hidden) {
                    const { line } = source.getLineAndCharacterOfPosition(
                        node.getStart(source),
                    );
                    violations.push(`${file}:${line + 1}: ${node.text}`);
                }
            }
            ts.forEachChild(node, visit);
        };
        visit(source);
    }
    assert.deepEqual(violations, []);
});

test("shared spinner keeps labels hidden, respects sizing, and media distinguishes loading from empty", async () => {
    const server = await createServer({
        configFile: false,
        resolve: {
            alias: {
                "@admin": resolve("platform/core/base/resources/js/admin"),
            },
        },
        esbuild: { jsx: "automatic" },
        logLevel: "silent",
        server: { middlewareMode: true, watch: null },
        appType: "custom",
    });
    try {
        const { LoadingIndicator } = await server.ssrLoadModule(
            "/platform/core/base/resources/js/admin/components/loading-indicator.tsx",
        );
        const markup = renderToStaticMarkup(
            createElement(LoadingIndicator, {
                label: "Đang tải ảnh…",
                className: "flex min-h-60",
                iconClassName: "size-5",
            }),
        );
        assert.match(markup, /role="status"/);
        assert.match(markup, /<span class="sr-only">Đang tải ảnh…<\/span>/);
        assert.match(markup, /<svg[^>]*aria-hidden="true"/);
        assert.match(markup, /size-5/);
        assert.match(markup, /motion-reduce:animate-none/);
        assert.doesNotMatch(
            renderToStaticMarkup(
                createElement(LoadingIndicator, {
                    label: "Loading…",
                    announce: false,
                }),
            ),
            /role="status"/,
        );

        const { Button } = await server.ssrLoadModule(
            "/platform/core/base/resources/js/admin/components/ui/button.tsx",
        );
        const button = renderToStaticMarkup(
            createElement(Button, { loading: true }, "Export"),
        );
        assert.match(button, /disabled=""/);
        assert.match(button, /aria-busy="true"/);
        assert.equal((button.match(/animate-spin/g) ?? []).length, 1);
        assert.match(button, />Export<\/button>/);

        const { MediaBrowser } = await server.ssrLoadModule(
            "/platform/core/media/resources/js/admin/components/media-browser.tsx",
        );
        const media = (loading) =>
            renderToStaticMarkup(
                createElement(App, {
                    initialPage: {
                        component: "Media",
                        url: "/admin/media",
                        props: {},
                        version: null,
                    },
                    initialComponent: () =>
                        createElement(MediaBrowser, {
                            permissions: [],
                            view: "all_media",
                            busy: false,
                            loading,
                            all: [],
                            grid: true,
                            selected: [],
                            selectionAnchor: { current: null },
                            upload: async () => {},
                            toggle: () => {},
                            open: () => {},
                            setSelected: () => {},
                        }),
                }),
            );
        const pending = media(true);
        assert.match(pending, /aria-busy="true"/);
        assert.match(pending, /animate-spin/);
        assert.doesNotMatch(pending, /No media found/);
        const empty = media(false);
        assert.doesNotMatch(empty, /animate-spin/);
        assert.match(empty, /No media found/);
    } finally {
        await server.close();
    }
});
