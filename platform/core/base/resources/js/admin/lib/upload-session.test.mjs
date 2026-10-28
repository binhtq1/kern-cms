import test from "node:test";
import assert from "node:assert/strict";
import { createUploadSession } from "./upload-session.ts";

test("closing an upload session rejects late success and progress", () => {
    const session = createUploadSession();
    const upload = session.begin();
    assert.equal(session.active(upload), true);
    session.cancel();
    assert.equal(upload.signal.aborted, true);
    assert.equal(session.active(upload), false);
    assert.equal(session.finish(upload), false);
});

test("an old upload cannot clear a reopened dialog's new upload", () => {
    const session = createUploadSession();
    const old = session.begin();
    session.cancel();
    const next = session.begin();
    assert.equal(session.finish(old), false);
    assert.equal(session.active(next), true);
    assert.equal(session.finish(next), true);
    assert.equal(session.active(next), false);
});

test("restarting aborts previous work and finishing requires matching identity", () => {
    const session = createUploadSession();
    const old = session.begin();
    const next = session.begin();
    assert.equal(old.signal.aborted, true);
    assert.equal(session.active(old), false);
    assert.equal(session.finish(old), false);
    assert.equal(session.active(next), true);
    session.cancel();
    session.cancel();
    assert.equal(next.signal.aborted, true);
});
