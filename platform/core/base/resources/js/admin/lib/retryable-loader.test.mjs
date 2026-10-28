import test from "node:test";
import assert from "node:assert/strict";
import { createRetryableLoader } from "./retryable-loader.ts";

test("shares one in-flight import and caches its successful module", async () => {
    let attempts = 0;
    const module = { RichEditor: class RichEditor {} };
    const loader = createRetryableLoader(async () => {
        attempts++;
        await Promise.resolve();
        return module;
    });

    const first = loader.load();
    const second = loader.load();
    assert.strictEqual(first, second);
    assert.strictEqual(await first, module);
    assert.strictEqual(await loader.load(), module);
    assert.equal(attempts, 1);
});

test("reset after a failed lazy import permits another attempt", async () => {
    let attempts = 0;
    const recovered = { default: "editor" };
    const loader = createRetryableLoader(async () => {
        attempts++;
        if (attempts === 1) throw new TypeError("chunk unavailable");
        return recovered;
    });

    const failed = loader.load();
    await assert.rejects(failed, /chunk unavailable/);
    assert.strictEqual(
        loader.load(),
        failed,
        "failure remains stable until explicit reset",
    );
    loader.reset();
    assert.strictEqual(await loader.load(), recovered);
    assert.equal(attempts, 2);
});

test("reset does not let a stale completion overwrite a newer attempt", async () => {
    let resolveOld;
    let attempts = 0;
    const oldResult = { version: "old" };
    const newResult = { version: "new" };
    const loader = createRetryableLoader(() => {
        attempts++;
        if (attempts === 1)
            return new Promise((resolve) => {
                resolveOld = resolve;
            });
        return Promise.resolve(newResult);
    });

    const oldRequest = loader.load();
    loader.reset();
    assert.strictEqual(await loader.load(), newResult);
    resolveOld(oldResult);
    assert.strictEqual(await oldRequest, oldResult);
    assert.strictEqual(await loader.load(), newResult);
    assert.equal(attempts, 2);
});
