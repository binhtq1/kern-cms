import test from "node:test";
import { createRequire } from "node:module";
import { pathToFileURL } from "node:url";
import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import assert from "node:assert/strict";
const requireFromReact = createRequire(import.meta.resolve("@inertiajs/react"));
const coreRoot = requireFromReact.resolve
    .paths("@inertiajs/core")
    .map((path) => join(path, "@inertiajs/core"))
    .find((path) => existsSync(join(path, "package.json")));
const coreManifest = JSON.parse(
    readFileSync(join(coreRoot, "package.json"), "utf8"),
);
const { HttpNetworkError, HttpCancelledError, HttpResponseError } =
    await import(
        pathToFileURL(join(coreRoot, coreManifest.exports["."].import)).href
    );
import { handleInertiaNetworkError } from "./inertia-network-error.ts";

function networkEvent(error) {
    return new CustomEvent("inertia:networkError", {
        cancelable: true,
        detail: { error },
    });
}

test("a network failure shows one error and suppresses the handled promise rejection", () => {
    const event = networkEvent(
        new HttpNetworkError(
            "Sensitive transport detail",
            "/admin/menus/edit/3",
        ),
    );
    let notices = 0;
    assert.equal(
        handleInertiaNetworkError(event, () => notices++),
        true,
    );
    assert.equal(event.defaultPrevented, true);
    assert.equal(notices, 1);
    assert.equal(
        handleInertiaNetworkError(event, () => notices++),
        false,
    );
    assert.equal(notices, 1);
});

test("already handled network failures do not generate another toast", () => {
    const event = networkEvent(new HttpNetworkError("Offline"));
    event.preventDefault();
    assert.equal(
        handleInertiaNetworkError(event, () => assert.fail("duplicate toast")),
        false,
    );
});

test("cancellations, HTTP responses and application exceptions retain their own error handling", () => {
    for (const error of [
        new HttpCancelledError(),
        new HttpResponseError("Bad response", { status: 500 }),
        new TypeError("Programming error"),
        { name: "HttpNetworkError", code: "ERR_NETWORK" },
        Object.assign(new Error("Unrelated network code"), {
            code: "ERR_NETWORK",
        }),
        Object.assign(new Error("Missing network code"), {
            name: "HttpNetworkError",
        }),
    ]) {
        const event = networkEvent(error);
        assert.equal(
            handleInertiaNetworkError(event, () => assert.fail("wrong toast")),
            false,
        );
        assert.equal(event.defaultPrevented, false);
    }
});

test("network errors from the actual React-resolved Inertia core are handled across package copies", async () => {
    const requireFromReact = createRequire(
        import.meta.resolve("@inertiajs/react"),
    );
    const packageRoot = requireFromReact.resolve
        .paths("@inertiajs/core")
        .map((path) => join(path, "@inertiajs/core"))
        .find((path) => existsSync(join(path, "package.json")));
    assert.ok(packageRoot);
    const manifest = JSON.parse(
        readFileSync(join(packageRoot, "package.json"), "utf8"),
    );
    const actualCore = await import(
        pathToFileURL(join(packageRoot, manifest.exports["."].import)).href
    );
    const event = networkEvent(
        new actualCore.HttpNetworkError("Network error", "/admin/menus/edit/4"),
    );
    let notices = 0;
    assert.equal(
        handleInertiaNetworkError(event, () => notices++),
        true,
    );
    assert.equal(event.defaultPrevented, true);
    assert.equal(notices, 1);
});
