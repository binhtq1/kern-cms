import test from "node:test";
import assert from "node:assert/strict";
import { createBootstrapGuard } from "./bootstrap-guard.ts";

test("pre-setup lazy page rejection reports locally and renders one fallback", () => {
    const errors = [];
    let fallbackRenders = 0;
    const guard = createBootstrapGuard({
        report: (error) => errors.push(error),
        renderFailure: () => fallbackRenders++,
    });
    const error = new TypeError("private module path and message");

    guard.reject(error);
    guard.reject(new Error("duplicate rejection"));

    assert.deepEqual(errors, [error], "startup rejection is reported once");
    assert.equal(fallbackRenders, 1, "the minimal fallback is mounted once");
});

test("post-setup rejection reports without mounting a second root", () => {
    const errors = [];
    let fallbackRenders = 0;
    const guard = createBootstrapGuard({
        report: (error) => errors.push(error),
        renderFailure: () => fallbackRenders++,
    });
    guard.didSetup();
    const error = new Error("later deferred resolve failed");

    guard.reject(error);

    assert.deepEqual(errors, [error]);
    assert.equal(
        fallbackRenders,
        0,
        "already-mounted Inertia app must not be replaced",
    );
});

test("reporter failure cannot prevent the accessible startup fallback", () => {
    let fallbackRenders = 0;
    const guard = createBootstrapGuard({
        report: () => {
            throw new Error("reporter unavailable");
        },
        renderFailure: () => fallbackRenders++,
    });

    assert.doesNotThrow(() => guard.reject(new Error("page resolve failed")));
    assert.equal(fallbackRenders, 1);
});
