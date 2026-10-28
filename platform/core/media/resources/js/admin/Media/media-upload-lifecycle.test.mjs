import test from "node:test";
import assert from "node:assert/strict";
import { runMediaUpload } from "./media-upload-lifecycle.ts";
function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => {
        resolve = yes;
        reject = no;
    });
    return { promise, resolve, reject };
}
function fixture() {
    let owner = true,
        context = true,
        cancelled = false;
    const state = {
        selected: ["new-folder-selection"],
        progress: 40,
        busy: true,
        completed: 0,
        errors: [],
        refreshes: 0,
        released: 0,
    };
    return {
        state,
        changeContext: () => {
            context = false;
        },
        supersede: () => {
            owner = false;
        },
        abort: () => {
            cancelled = true;
        },
        lifecycle: {
            isOwner: () => owner,
            isCurrent: () => owner && context && !cancelled,
            isCancelled: () => cancelled,
            complete: () => {
                state.completed++;
            },
            fail: (error) => {
                state.errors.push(error.message);
            },
            release: () => {
                state.released++;
                state.busy = false;
                state.progress = null;
            },
            refresh: () => {
                state.refreshes++;
                state.selected = [];
            },
        },
    };
}
test("late upload completion releases its progress without refreshing or clearing another folder selection", async () => {
    const upload = deferred();
    const f = fixture();
    const pending = runMediaUpload(() => upload.promise, f.lifecycle);
    f.changeContext();
    upload.resolve();
    await pending;
    assert.deepEqual(f.state.selected, ["new-folder-selection"]);
    assert.equal(f.state.refreshes, 0);
    assert.equal(f.state.completed, 0);
    assert.equal(f.state.busy, false);
    assert.equal(f.state.progress, null);
});
test("superseded completion cannot clear a newer upload controller or progress", async () => {
    const upload = deferred();
    const f = fixture();
    const pending = runMediaUpload(() => upload.promise, f.lifecycle);
    f.supersede();
    upload.resolve();
    await pending;
    assert.equal(f.state.released, 0);
    assert.equal(f.state.busy, true);
    assert.equal(f.state.progress, 40);
    assert.equal(f.state.refreshes, 0);
});
test("current success and partial failure refresh once while aborted requests never refresh", async () => {
    const success = fixture();
    await runMediaUpload(async () => {}, success.lifecycle);
    assert.equal(success.state.completed, 1);
    assert.equal(success.state.refreshes, 1);
    assert.equal(success.state.released, 1);
    const failed = fixture();
    await runMediaUpload(async () => {
        throw new Error("Upload failed");
    }, failed.lifecycle);
    assert.deepEqual(failed.state.errors, ["Upload failed"]);
    assert.equal(failed.state.refreshes, 1);
    assert.equal(failed.state.released, 1);
    const cancelled = fixture();
    cancelled.abort();
    await runMediaUpload(async () => {
        throw new Error("cancelled");
    }, cancelled.lifecycle);
    assert.equal(cancelled.state.refreshes, 0);
    assert.deepEqual(cancelled.state.errors, []);
    assert.equal(cancelled.state.completed, 0);
    assert.equal(cancelled.state.released, 1);
});
