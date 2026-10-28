import test from "node:test";
import assert from "node:assert/strict";
import {
    createUploadProgress,
    chunkUploadPercent,
    formatUploadSize,
} from "./upload-progress.ts";

function fixture() {
    let items = [];
    const progress = createUploadProgress((value) => {
        items = value;
    });
    return { progress, items: () => items };
}
const files = [
    { name: "a.jpg", size: 10 },
    { name: "b.jpg", size: 20 },
    { name: "a.jpg", size: 30 },
];

test("each queued file has its own identity and remains below 100 until server acknowledgment", () => {
    const f = fixture();
    const batch = f.progress.start(files);
    assert.deepEqual(
        f.items().map((item) => item.status),
        ["queued", "queued", "queued"],
    );
    assert.equal(new Set(f.items().map((item) => item.id)).size, 3);
    batch.update(0, { status: "uploading", percent: 105 });
    assert.equal(f.items()[0].percent, 99);
    batch.update(0, { percent: 20 });
    assert.equal(f.items()[0].percent, 99);
    batch.update(0, { status: "uploaded" });
    assert.equal(f.items()[0].percent, 100);
    batch.update(0, { percent: 35 });
    assert.equal(f.items()[0].percent, 100);
});

test("file failures and cancellation retain completed results and reject late progress", () => {
    const f = fixture();
    const batch = f.progress.start(files);
    batch.update(0, { status: "uploaded" });
    batch.update(1, { status: "error", error: "File too large" });
    batch.cancel();
    batch.update(2, { status: "uploaded" });
    assert.deepEqual(
        f.items().map((item) => item.status),
        ["uploaded", "error", "cancelled"],
    );
    assert.equal(f.items()[1].error, "File too large");
});

test("superseded, dismissed and disposed batches cannot update a new queue", () => {
    const f = fixture();
    const old = f.progress.start(files);
    const latest = f.progress.start([files[0]]);
    const snapshot = f.items();
    old.update(0, { status: "uploaded" });
    old.cancel();
    assert.equal(f.items(), snapshot);
    f.progress.clear();
    latest.update(0, { percent: 60 });
    assert.deepEqual(f.items(), []);
    const unmounted = f.progress.start(files);
    const beforeDispose = f.items();
    f.progress.dispose();
    unmounted.update(0, { status: "uploaded" });
    unmounted.cancel();
    assert.equal(f.items(), beforeDispose);
});

test("chunk progress uses bytes, handles unequal final chunks and waits for server completion", () => {
    assert.equal(chunkUploadPercent(10, 4, 0, { loaded: 4 }), 40);
    assert.equal(chunkUploadPercent(10, 4, 1, { loaded: 4 }), 80);
    assert.equal(chunkUploadPercent(10, 4, 2, { loaded: 1 }), 90);
    assert.equal(chunkUploadPercent(10, 4, 2, { progress: 0.5 }), 90);
    assert.equal(chunkUploadPercent(10, 4, 2, { loaded: 2 }), 99);
    // Multipart overhead must not count as uploaded file bytes.
    assert.equal(chunkUploadPercent(10, 4, 1, { loaded: 50, total: 100 }), 60);
    assert.equal(chunkUploadPercent(0, 1, 0, { loaded: 0 }), 0);
    assert.equal(formatUploadSize(0), "0 B");
    assert.equal(formatUploadSize(1024), "1.0 KB");
    assert.equal(formatUploadSize(1024 ** 2 * 2.5), "2.5 MB");
});

test("retry resets only the selected failed file while preserving successful and other failed rows", () => {
    const f = fixture();
    const initial = f.progress.start(files);
    initial.update(0, { status: "uploaded" });
    initial.update(1, { status: "error", percent: 60, error: "Network error" });
    initial.update(2, { status: "error", error: "Rejected" });
    const before = f.items();
    const retry = f.progress.retry(before[1].id);
    assert.equal(retry.index, 1);
    assert.equal(f.items()[0], before[0]);
    assert.equal(f.items()[2], before[2]);
    assert.equal(f.items()[1].id, before[1].id);
    assert.equal(f.items()[1].status, "uploading");
    assert.equal(f.items()[1].percent, 0);
    assert.equal(f.items()[1].error, undefined);
    assert.equal(f.progress.retry(before[1].id), null);
    assert.equal(f.progress.retry(before[2].id), null);
    retry.update(1, { status: "uploaded" });
    assert.deepEqual(
        f.items().map((item) => item.status),
        ["uploaded", "uploaded", "error"],
    );
    assert.equal(f.items()[1].percent, 100);
});

test("retry rejects active, missing, successful and cancelled rows", () => {
    const f = fixture();
    const batch = f.progress.start(files);
    batch.update(0, { status: "error", error: "Temporary error" });
    assert.equal(f.progress.retry(f.items()[0].id), null);
    batch.update(1, { status: "uploaded" });
    batch.cancel();
    assert.equal(f.progress.retry("missing"), null);
    assert.equal(f.progress.retry(f.items()[1].id), null);
    assert.equal(f.progress.retry(f.items()[2].id), null);
    const id = f.items()[0].id;
    f.progress.clear();
    assert.equal(f.progress.retry(id), null);
});

test("late progress and cancellation from older attempts cannot overwrite a retry", () => {
    const f = fixture();
    const initial = f.progress.start([files[0]]);
    initial.update(0, { status: "error", percent: 70, error: "Timeout" });
    const id = f.items()[0].id;
    const retry = f.progress.retry(id);
    initial.update(0, { status: "uploaded" });
    initial.cancel();
    assert.equal(f.items()[0].status, "uploading");
    assert.equal(f.items()[0].percent, 0);
    retry.update(0, { status: "error", error: "Timeout again" });
    const next = f.progress.retry(id);
    retry.cancel();
    retry.update(0, { status: "uploaded" });
    assert.equal(f.items()[0].status, "uploading");
    next.cancel();
    assert.equal(f.items()[0].status, "cancelled");
});
