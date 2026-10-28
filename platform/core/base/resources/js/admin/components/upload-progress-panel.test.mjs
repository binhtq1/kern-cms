import test from "node:test";
import assert from "node:assert/strict";
import { resolve } from "node:path";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { App } from "@inertiajs/react";
import { createServer } from "vite";

test("upload panel shows per-file results safely and keeps cancellation separate from dismissal", async () => {
    const server = await createServer({
        configFile: false,
        resolve: {
            alias: {
                "@admin": resolve("platform/core/base/resources/js/admin"),
            },
        },
        esbuild: { jsx: "automatic" },
        server: { middlewareMode: true, watch: null },
        appType: "custom",
        logLevel: "silent",
    });
    try {
        const { UploadProgressPanel } = await server.ssrLoadModule(
            "/platform/core/base/resources/js/admin/components/upload-progress-panel.tsx",
        );
        const render = (items, inline = false, retryDisabled = false) =>
            renderToStaticMarkup(
                createElement(App, {
                    initialPage: {
                        component: "Media",
                        url: "/admin/media",
                        props: {},
                        version: null,
                    },
                    initialComponent: () =>
                        createElement(UploadProgressPanel, {
                            items,
                            inline,
                            onCancel: () => {},
                            onDismiss: () => {},
                            onRetry: () => {},
                            retryDisabled,
                        }),
                }),
            );
        const items = [
            {
                id: "1",
                name: "cover.jpg",
                size: 2048,
                percent: 100,
                status: "uploaded",
            },
            {
                id: "2",
                name: "<script>.png",
                size: 1024,
                percent: 42,
                status: "uploading",
            },
            {
                id: "3",
                name: "document.pdf",
                size: 1,
                percent: 0,
                status: "queued",
            },
        ];
        const pending = render(items);
        assert.match(pending, /fixed bottom-4 end-4/);
        assert.match(pending, /2\.0 KB/);
        assert.match(pending, /1 \/ 3 files uploaded/);
        assert.match(pending, /value="42"/);
        assert.match(pending, /Cancel upload/);
        const cancelButton = pending.match(
            /<button[^>]*aria-label="Cancel upload"[^>]*>[\s\S]*?<\/button>/,
        )?.[0];
        assert.ok(cancelButton);
        assert.match(cancelButton, /title="Cancel upload"/);
        assert.match(cancelButton, /<svg[^>]*aria-hidden="true"/);
        assert.match(cancelButton, /size-11/);
        assert.doesNotMatch(cancelButton, />\s*Cancel upload\s*</);
        const collapseButton = pending.match(
            /<button[^>]*aria-label="Collapse upload progress"[^>]*>/,
        )?.[0];
        assert.ok(collapseButton);
        assert.match(collapseButton, /title="Collapse upload progress"/);
        assert.match(collapseButton, /aria-expanded="true"/);
        assert.doesNotMatch(pending, /Close upload progress/);
        assert.match(pending, /&lt;script&gt;\.png/);
        assert.doesNotMatch(pending, /<script>/);
        const failed = render(
            [{ ...items[1], status: "error", error: "File too large" }],
            true,
        );
        assert.match(failed, /File too large/);
        assert.match(failed, /Upload failed/);
        assert.match(failed, /Close upload progress/);
        const closeButton = failed.match(
            /<button[^>]*aria-label="Close upload progress"[^>]*>/,
        )?.[0];
        assert.ok(closeButton);
        assert.match(closeButton, /title="Close upload progress"/);
        assert.doesNotMatch(failed, /Cancel upload|fixed bottom/);
        assert.match(failed, /aria-label="Retry upload: &lt;script&gt;\.png"/);
        const retryButton = failed.match(
            /<button[^>]*aria-label="Retry upload:[^>]*>[\s\S]*?<\/button>/,
        )?.[0];
        assert.ok(retryButton);
        assert.match(retryButton, /title="Retry"/);
        assert.match(retryButton, /<svg[^>]*aria-hidden="true"/);
        assert.doesNotMatch(retryButton, />\s*Retry\s*</);
        assert.match(retryButton, /size-11/);
        assert.match(failed, /role="alert"/);
        const failedRow = {
            ...items[2],
            status: "error",
            error: "Network error",
        };
        const busyRetry = render([items[0], items[1], failedRow]);
        const retryTag = busyRetry.match(
            /<button[^>]*aria-label="Retry upload: document.pdf"[^>]*>/,
        )?.[0];
        assert.ok(retryTag?.includes('disabled=""'));
        const settledRetry = render([items[0], failedRow]);
        const enabledTag = settledRetry.match(
            /<button[^>]*aria-label="Retry upload: document.pdf"[^>]*>/,
        )?.[0];
        assert.ok(enabledTag && !enabledTag.includes('disabled=""'));
        const externallyBusy = render([items[0], failedRow], false, true);
        assert.ok(
            externallyBusy
                .match(
                    /<button[^>]*aria-label="Retry upload: document.pdf"[^>]*>/,
                )?.[0]
                .includes('disabled=""'),
        );
        assert.equal(render([]), "");
    } finally {
        await server.close();
    }
});
