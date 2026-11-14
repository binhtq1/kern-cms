import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath, URL } from "node:url";
import { existsSync, readFileSync } from "node:fs";
import { createHash } from "node:crypto";
import { globSync } from "glob";
import {
    createPageRegistry,
    type PageRegistration,
} from "./vendor/binhtq1/kern-core/core/base/resources/js/admin/lib/page-registry";

export default defineConfig({
    plugins: [
        {
            name: "admin-extension-registry",
            generateBundle() {
                const registrationFiles = globSync([
                    "vendor/binhtq1/kern-core/{core,packages}/*/resources/js/admin/registration.json",
                    "platform/plugins/*/resources/js/admin/registration.json",
                ]).sort();
                const registrations: Record<string, PageRegistration> = {};
                const sources: Record<string, string> = {};
                for (const file of registrationFiles) {
                    const source = readFileSync(file, "utf8");
                    registrations[`../../${file}`] = JSON.parse(source);
                    sources[file] = source;
                }
                const files = globSync([
                    "vendor/binhtq1/kern-core/{core,packages}/*/resources/js/admin/**/*.tsx",
                    "platform/plugins/*/resources/js/admin/**/*.tsx",
                ]).map((file) => `../../${file}`);
                const pages = createPageRegistry(registrations, files);
                const packages = Object.fromEntries(
                    registrationFiles.map((file) => {
                        const packagePath = file
                            .split("/")
                            .slice(
                                file.startsWith("vendor/") ? 3 : 1,
                                file.startsWith("vendor/") ? 5 : 3,
                            )
                            .join("/");
                        const prefix = `../../${file.slice(0, -"registration.json".length)}`;
                        return [
                            packagePath,
                            {
                                hash: createHash("sha256")
                                    .update(sources[file])
                                    .digest("hex"),
                                pages: Object.entries(pages)
                                    .filter(([, path]) =>
                                        path.startsWith(prefix),
                                    )
                                    .map(([name]) => name)
                                    .sort(),
                            },
                        ];
                    }),
                );
                this.emitFile({
                    type: "asset",
                    fileName: "admin-registry.json",
                    source: JSON.stringify({ version: 1, packages }),
                });
            },
        },
        laravel({
            input: [
                "resources/js/admin.tsx",
                ...(existsSync("resources/js/member.tsx") &&
                existsSync(
                    "platform/plugins/member/resources/js/account/Dashboard.tsx",
                )
                    ? ["resources/js/member.tsx"]
                    : []),
            ],
            buildDirectory: "build/admin",
            hotFile: "public/admin.hot",
            refresh: ["platform/**/resources/views/admin/**"],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        dedupe: ["@tiptap/core", "@tiptap/pm"],
        alias: {
            "@admin": fileURLToPath(
                new URL(
                    "./vendor/binhtq1/kern-core/core/base/resources/js/admin",
                    import.meta.url,
                ),
            ),
        },
    },
    build: {
        rollupOptions: {
            output: {
                onlyExplicitManualChunks: true,
                manualChunks(id) {
                    // Keep editor dependencies cacheable without pulling shared
                    // application dependencies into the lazy editor chunk.
                    if (
                        /\/node_modules\/(?:@tiptap\/|prosemirror-[^/]+\/)/.test(
                            id.replaceAll("\\", "/"),
                        )
                    ) {
                        return "editor-vendor";
                    }
                },
            },
        },
    },
});
