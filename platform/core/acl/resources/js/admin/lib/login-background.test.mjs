import test from "node:test";
import assert from "node:assert/strict";
import { resolveLoginBackground } from "./login-background.ts";

const resource =
    "platform/core/acl/resources/js/admin/assets/backgrounds/1.jpg";

test("empty values resolve to no background", () => {
    assert.equal(resolveLoginBackground(null, {}), null);
    assert.equal(resolveLoginBackground(undefined, {}), null);
    assert.equal(resolveLoginBackground("", {}), null);
});

test("custom absolute and relative URLs pass through unchanged", () => {
    for (const value of [
        "https://cdn.example.test/login.jpg",
        "/uploads/login.jpg",
        "images/login.jpg",
    ]) {
        assert.equal(resolveLoginBackground(value, {}), value);
    }
});

test("owned resource identifiers resolve only through their exact Vite URL", () => {
    const viteUrl = "/build/assets/login-background-abc123.jpg";
    assert.equal(
        resolveLoginBackground(resource, { [`/${resource}`]: viteUrl }),
        viteUrl,
    );
    assert.equal(resolveLoginBackground(resource, {}), null);
    assert.equal(
        resolveLoginBackground(resource, { [`/${resource}`]: 42 }),
        null,
    );
});

test("nearby but unowned asset paths remain custom values", () => {
    const value =
        "platform/core/acl/resources/js/admin/assets/backgrounds-archive/1.jpg";
    assert.equal(resolveLoginBackground(value, {}), value);
});
