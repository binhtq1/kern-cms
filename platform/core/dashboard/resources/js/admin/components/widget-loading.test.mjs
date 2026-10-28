import test from "node:test";
import assert from "node:assert/strict";
import { resolve } from "node:path";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { createServer } from "vite";
import { App } from "@inertiajs/react";

test("widget loading renders a spinner with a screen-reader-only contextual status", async () => {
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
        const { WidgetLoading } = await server.ssrLoadModule(
            "/platform/core/dashboard/resources/js/admin/components/widget-loading.tsx",
        );
        const markup = renderToStaticMarkup(
            createElement(WidgetLoading, {
                label: "Recent posts: Loading…",
            }),
        );
        assert.match(markup, /role="status"/);
        assert.match(markup, /<svg[^>]*aria-hidden="true"/);
        assert.match(markup, /animate-spin/);
        assert.match(markup, /motion-reduce:animate-none/);
        assert.match(
            markup,
            /<span class="sr-only">Recent posts: Loading…<\/span>/,
        );
        assert.match(markup, /min-h-48/);

        const chartMarkup = renderToStaticMarkup(
            createElement(WidgetLoading, {
                label: "Đang tải biểu đồ…",
                className: "min-h-64 rounded border p-4",
            }),
        );
        assert.match(chartMarkup, /min-h-64/);
        assert.doesNotMatch(chartMarkup, /min-h-48/);
        assert.match(
            chartMarkup,
            /<span class="sr-only">Đang tải biểu đồ…<\/span>/,
        );

        const { WidgetPanel } = await server.ssrLoadModule(
            "/platform/core/dashboard/resources/js/admin/components/widget-panel.tsx",
        );
        const panelMarkup = renderToStaticMarkup(
            createElement(App, {
                initialPage: {
                    component: "Dashboard",
                    url: "/admin",
                    props: {},
                    version: null,
                },
                initialComponent: () =>
                    createElement(WidgetPanel, {
                        widget: {
                            id: 1,
                            name: "recent-posts",
                            title: "Recent posts",
                            url: "/admin/widgets/posts",
                            type: "widget",
                            visible: true,
                        },
                        settingUrl: "/admin/widgets/settings",
                        controls: null,
                    }),
            }),
        );
        assert.match(panelMarkup, /aria-busy="true"/);
        assert.equal((panelMarkup.match(/animate-spin/g) ?? []).length, 1);
        assert.match(
            panelMarkup,
            /<span class="sr-only">Recent posts: Loading…<\/span>/,
        );
    } finally {
        await server.close();
    }
});
