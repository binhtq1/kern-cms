import test from "node:test";
import assert from "node:assert/strict";
import { createRequire } from "node:module";
import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { pathToFileURL } from "node:url";
const requireFromReact = createRequire(import.meta.resolve("@inertiajs/react"));
const coreRoot = requireFromReact.resolve
    .paths("@inertiajs/core")
    .map((path) => join(path, "@inertiajs/core"))
    .find((path) => existsSync(join(path, "package.json")));
const coreManifest = JSON.parse(
    readFileSync(join(coreRoot, "package.json"), "utf8"),
);
const { mergeDataIntoQueryString } = await import(
    pathToFileURL(join(coreRoot, coreManifest.exports["."].import)).href
);
import { adminQueryOptions } from "./utils.ts";

test("admin GET visits encode each filter as one indexed Laravel array row", () => {
    const filters = [
        { field: "name", operator: "like", value: "Tên & title" },
        { field: "created_at", operator: ">=", value: "2026-01-01" },
    ];
    const [url, body] = mergeDataIntoQueryString(
        "get",
        "https://example.test/admin/pages?ref_lang=vi",
        { advanced: filters, page: 1 },
        adminQueryOptions.queryStringArrayFormat,
    );
    const query = new URL(url).searchParams;
    assert.equal(query.get("ref_lang"), "vi");
    assert.equal(query.get("page"), "1");
    for (const [index, row] of filters.entries()) {
        for (const [field, value] of Object.entries(row)) {
            assert.equal(query.get(`advanced[${index}][${field}]`), value);
        }
    }
    assert.equal(query.has("advanced[][field]"), false);
    assert.deepEqual(body, {});
});
