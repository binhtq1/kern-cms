import test from "node:test";
import assert from "node:assert/strict";
import { createLatestRequest } from "./latest-request.ts";

function deferred() {
    let resolve, reject;
    const promise = new Promise((success, failure) => {
        resolve = success;
        reject = failure;
    });
    return { promise, resolve, reject };
}

function callbacks(events, name) {
    return {
        onSuccess: (value) => events.push([name, "success", value]),
        onError: (reason) => events.push([name, "error", reason.message]),
        onSettled: () => events.push([name, "settled"]),
    };
}

test("a late preview cannot overwrite a newer block or stop its loading state", async () => {
    const request = createLatestRequest();
    const old = deferred();
    const current = deferred();
    const events = [];
    let oldSignal;
    const first = request.run(
        (signal) => {
            oldSignal = signal;
            return old.promise;
        },
        callbacks(events, "old"),
    );
    const second = request.run(
        () => current.promise,
        callbacks(events, "current"),
    );
    assert.equal(oldSignal.aborted, true);
    old.resolve("stale HTML");
    await first;
    assert.deepEqual(events, []);
    current.resolve("current HTML");
    await second;
    assert.deepEqual(events, [
        ["current", "success", "current HTML"],
        ["current", "settled"],
    ]);
});

test("closing or changing a block ignores a response even when transport ignores abort", async () => {
    const request = createLatestRequest();
    const response = deferred();
    const events = [];
    let signal;
    const pending = request.run(
        (value) => {
            signal = value;
            return response.promise;
        },
        callbacks(events, "preview"),
    );
    request.cancel();
    assert.equal(signal.aborted, true);
    response.resolve("closed block HTML");
    await pending;
    assert.deepEqual(events, []);
});

test("late failures from a cancelled preview cannot show an error in another block", async () => {
    const request = createLatestRequest();
    const response = deferred();
    const events = [];
    const pending = request.run(
        () => response.promise,
        callbacks(events, "old"),
    );
    request.cancel();
    response.reject(new Error("old server error"));
    await pending;
    assert.deepEqual(events, []);
});

test("a current failure is reported and releases loading for a successful retry", async () => {
    const request = createLatestRequest();
    const events = [];
    await request.run(
        async () => {
            throw new Error("server error");
        },
        callbacks(events, "first"),
    );
    await request.run(async () => "retry HTML", callbacks(events, "retry"));
    assert.deepEqual(events, [
        ["first", "error", "server error"],
        ["first", "settled"],
        ["retry", "success", "retry HTML"],
        ["retry", "settled"],
    ]);
});
