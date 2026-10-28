import test from "node:test";
import assert from "node:assert/strict";
import {
    createClientErrorReporter,
    installClientErrorReporting,
} from "./client-errors.ts";

test("reporter deduplicates bounded error categories and excludes message data", () => {
    const sent = [];
    const reporter = createClientErrorReporter(
        (record) => sent.push(record),
        2,
    );
    const sensitive = new Error(
        "/admin/users?email=person@example.test token=secret",
    );
    reporter.report(sensitive, "boundary");
    reporter.report(new Error("another private message"), "boundary");
    reporter.report(new TypeError("private stack detail"), "window");

    assert.deepEqual(reporter.records(), [
        { source: "boundary", name: "Error", count: 2 },
        { source: "window", name: "TypeError", count: 1 },
    ]);
    assert.deepEqual(sent, [
        { source: "boundary", name: "Error", count: 1 },
        { source: "window", name: "TypeError", count: 1 },
    ]);
    assert.doesNotMatch(
        JSON.stringify([...sent, ...reporter.records()]),
        /email|secret|private|stack|admin\/users/,
    );

    reporter.report(new RangeError("unreported category"), "editor");
    assert.deepEqual(
        reporter.records(),
        [
            { source: "window", name: "TypeError", count: 1 },
            { source: "editor", name: "RangeError", count: 1 },
        ],
        "oldest category is evicted when the bounded registry is full",
    );
});

test("unknown error names and invalid sources are reduced to safe categories", () => {
    const reporter = createClientErrorReporter();
    const hostile = new Error("ignored");
    hostile.name = "Token=secret";
    reporter.report(hostile, "boundary");
    reporter.report({ message: "PII" }, "analytics");
    assert.deepEqual(reporter.records(), [
        { source: "boundary", name: "Error", count: 1 },
        { source: "analytics", name: "Error", count: 1 },
    ]);
});

test("runtime-invalid sources and unsafe error name accessors cannot leak or crash", () => {
    const reporter = createClientErrorReporter();
    const hostileSource = "analytics?token=private@example.test";
    const hostileName = new Error("message must not be read");
    Object.defineProperty(hostileName, "name", {
        get() {
            throw new Error("name getter must be contained");
        },
    });

    assert.doesNotThrow(() => {
        reporter.report(hostileName, "boundary");
        reporter.report(new Error("private"), hostileSource);
    });
    assert.deepEqual(reporter.records(), [
        { source: "boundary", name: "Error", count: 2 },
    ]);
    assert.doesNotMatch(
        JSON.stringify(reporter.records()),
        /token|private|example\.test/,
    );
});

test("error name is captured once before it is sanitized", () => {
    const reporter = createClientErrorReporter();
    const values = ["Error", "token=private@example.test"];
    const error = new Error("ignored");
    Object.defineProperty(error, "name", {
        get() {
            return values.shift();
        },
    });

    reporter.report(error, "editor");
    assert.deepEqual(reporter.records(), [
        { source: "editor", name: "Error", count: 1 },
    ]);
    assert.doesNotMatch(
        JSON.stringify(reporter.records()),
        /token|private|example\.test/,
    );
});

test("event listeners install once, report error/rejection, and dispose idempotently", () => {
    const target = new EventTarget();
    const reporter = createClientErrorReporter();
    const dispose = installClientErrorReporting(target, reporter);
    assert.strictEqual(installClientErrorReporting(target, reporter), dispose);

    const error = new Event("error");
    error.error = new TypeError("not retained");
    target.dispatchEvent(error);
    const rejection = new Event("unhandledrejection");
    rejection.reason = new RangeError("not retained either");
    target.dispatchEvent(rejection);
    assert.deepEqual(reporter.records(), [
        { source: "window", name: "TypeError", count: 1 },
        { source: "rejection", name: "RangeError", count: 1 },
    ]);

    dispose();
    dispose();
    target.dispatchEvent(new Event("error"));
    assert.equal(reporter.records().length, 2);
    assert.strictEqual(
        installClientErrorReporting(target, reporter),
        installClientErrorReporting(target, reporter),
    );
});

test("report sink failures never interrupt application error handling", () => {
    const reporter = createClientErrorReporter(() => {
        throw new Error("sink failure");
    });
    assert.doesNotThrow(() =>
        reporter.report(new Error("private"), "boundary"),
    );
    assert.deepEqual(reporter.records(), [
        { source: "boundary", name: "Error", count: 1 },
    ]);
});

test("default reporting stays local and never invokes a network transport", () => {
    const previousFetch = globalThis.fetch;
    let requests = 0;
    globalThis.fetch = () => {
        requests++;
        throw new Error("unexpected network request");
    };
    try {
        const reporter = createClientErrorReporter();
        reporter.report(new Error("token=private@example.test"), "analytics");
        assert.equal(requests, 0);
        assert.deepEqual(reporter.records(), [
            { source: "analytics", name: "Error", count: 1 },
        ]);
    } finally {
        globalThis.fetch = previousFetch;
    }
});
