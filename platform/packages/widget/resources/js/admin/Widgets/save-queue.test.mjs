import test from "node:test";
import assert from "node:assert/strict";
import { createSaveQueue, committedLayout } from "./save-queue.ts";

const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => {
        resolve = yes;
        reject = no;
    });
    return { promise, resolve, reject };
};
const tick = () => new Promise((resolve) => setImmediate(resolve));

test("layout autosave preserves explicitly saved configurations while keeping other field drafts local", () => {
    const draft = [
        { id: "source", items: [] },
        {
            id: "destination",
            items: [
                { key: "A", data: { content: "unsaved text" } },
                { key: "B", data: { content: "saved text" } },
            ],
        },
    ];
    const committed = new Map([
        ["A", { content: "original text" }],
        ["B", { content: "saved text" }],
    ]);
    const snapshot = committedLayout(draft, committed);
    assert.equal(snapshot[1].items[0].data.content, "original text");
    assert.equal(snapshot[1].items[1].data.content, "saved text");
    assert.equal(draft[1].items[0].data.content, "unsaved text");
    assert.deepEqual(
        snapshot.map((area) => area.items.map((item) => item.key)),
        [[], ["A", "B"]],
    );
});

test("widget writes are serialized and coalesce waiting layouts without losing source and destination", async () => {
    const first = deferred();
    const writes = [],
        saved = [],
        states = [];
    const queue = createSaveQueue({
        save: async (snapshot) => {
            writes.push(snapshot);
            if (writes.length === 1) await first.promise;
        },
        onSaved: (snapshot) => saved.push(snapshot),
        onError: (error) => assert.fail(error),
        onState: (value) => states.push(value),
    });
    queue.enqueue({ source: ["A", "B"], destination: [] });
    queue.enqueue({ source: ["A"], destination: ["B"] });
    const latest = { source: [], destination: ["B", "A"] };
    queue.enqueue(latest);
    latest.destination.push("unsent mutation");
    assert.equal(writes.length, 1);
    first.resolve();
    await tick();
    assert.deepEqual(writes, [
        { source: ["A", "B"], destination: [] },
        { source: [], destination: ["B", "A"] },
    ]);
    assert.deepEqual(saved, writes);
    assert.deepEqual(states, [true, false]);
});
test("failure preserves the latest layout for explicit retry and never reports it saved", async () => {
    const first = deferred();
    const writes = [],
        saved = [],
        errors = [];
    const queue = createSaveQueue({
        save: async (snapshot) => {
            writes.push(snapshot);
            if (writes.length === 1) await first.promise;
        },
        onSaved: (value) => saved.push(value),
        onError: (value) => errors.push(value),
        onState: () => {},
    });
    queue.enqueue("old");
    queue.enqueue("latest");
    first.reject(new Error("network failed"));
    await tick();
    assert.deepEqual(writes, ["old"]);
    assert.deepEqual(saved, []);
    assert.equal(errors.length, 1);
    queue.retry();
    await tick();
    assert.deepEqual(writes, ["old", "latest"]);
    assert.deepEqual(saved, ["latest"]);
});
test("cancelling a locale scope aborts active work and ignores late responses and queued writes", async () => {
    const pending = deferred();
    const saved = [],
        states = [],
        writes = [];
    let signal;
    const queue = createSaveQueue({
        save: async (value, nextSignal) => {
            writes.push(value);
            signal = nextSignal;
            await pending.promise;
        },
        onSaved: (value) => saved.push(value),
        onError: () => assert.fail("cancel must not report an error"),
        onState: (value) => states.push(value),
    });
    queue.enqueue("en");
    queue.enqueue("queued en");
    queue.cancel();
    queue.enqueue("after dispose");
    pending.resolve();
    await tick();
    assert.equal(signal.aborted, true);
    assert.deepEqual(writes, ["en"]);
    assert.deepEqual(saved, []);
    assert.deepEqual(states, [true]);
});
