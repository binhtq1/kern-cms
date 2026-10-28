import test from "node:test";
import assert from "node:assert/strict";
import { createRoleRequests } from "./role-requests.ts";

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((success, failure) => {
        resolve = success;
        reject = failure;
    });
    return { promise, resolve, reject };
}

test("a newer same-row request aborts the old controller and owns cleanup", () => {
    const requests = createRoleRequests();
    const old = requests.begin(17);
    const replacement = requests.begin(17);

    assert.equal(old.signal.aborted, true);
    assert.equal(requests.owns(17, old), false);
    assert.equal(requests.owns(17, replacement), true);
    assert.equal(requests.has(17), true);

    requests.finish(17, old);
    assert.equal(
        requests.has(17),
        true,
        "stale finally must not clear the replacement",
    );
    assert.equal(requests.owns(17, replacement), true);
    requests.finish(17, replacement);
    assert.equal(requests.has(17), false);
});

test("stale row success and failure results cannot replace current assignment state", async () => {
    const requests = createRoleRequests();
    const state = new Map([[17, ["editor"]]]);
    const errors = new Map();
    const older = deferred();
    const olderController = requests.begin(17);
    const olderWork = older.promise.then(
        (roles) => {
            if (requests.owns(17, olderController)) state.set(17, roles);
        },
        (error) => {
            if (requests.owns(17, olderController))
                errors.set(17, error.message);
        },
    );

    const currentController = requests.begin(17);
    const current = deferred();
    const currentWork = current.promise.then(
        (roles) => {
            if (requests.owns(17, currentController)) state.set(17, roles);
        },
        (error) => {
            if (requests.owns(17, currentController))
                errors.set(17, error.message);
        },
    );
    current.resolve(["admin"]);
    await currentWork;
    older.resolve(["viewer"]);
    await olderWork;
    assert.deepEqual(state.get(17), ["admin"]);
    assert.deepEqual([...errors], []);

    const staleErrorController = requests.begin(17);
    const staleFailure = deferred();
    const staleFailureWork = staleFailure.promise.catch((error) => {
        if (requests.owns(17, staleErrorController))
            errors.set(17, error.message);
    });
    const newestController = requests.begin(17);
    const newest = deferred();
    const newestWork = newest.promise.then((roles) => {
        if (requests.owns(17, newestController)) state.set(17, roles);
    });
    staleFailure.reject(new Error("stale server failure"));
    await staleFailureWork;
    newest.resolve(["publisher"]);
    await newestWork;
    assert.deepEqual(state.get(17), ["publisher"]);
    assert.deepEqual(
        [...errors],
        [],
        "superseded errors must not surface on the row",
    );
});

test("row requests are isolated and dataset reset/unmount cancellation invalidates all old owners", async () => {
    const requests = createRoleRequests();
    const firstRow = requests.begin(1);
    const secondRow = requests.begin(2);
    const delayed = deferred();
    const values = [];
    const delayedWork = delayed.promise.then((value) => {
        if (requests.owns(1, firstRow)) values.push(value);
    });

    assert.equal(requests.owns(1, firstRow), true);
    assert.equal(requests.owns(2, secondRow), true);
    requests.cancel();
    assert.equal(firstRow.signal.aborted, true);
    assert.equal(secondRow.signal.aborted, true);
    assert.equal(requests.has(1), false);
    assert.equal(requests.has(2), false);
    assert.equal(requests.owns(1, firstRow), false);

    delayed.resolve("old dataset response");
    await delayedWork;
    assert.deepEqual(
        values,
        [],
        "old responses cannot update a refreshed dataset",
    );
});

test("finishing one row does not affect another row's active request", () => {
    const requests = createRoleRequests();
    const first = requests.begin(1);
    const second = requests.begin(2);

    requests.finish(1, first);

    assert.equal(requests.has(1), false);
    assert.equal(requests.owns(2, second), true);
});
