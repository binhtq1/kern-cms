import test from "node:test";
import assert from "node:assert/strict";
import { rememberMedia } from "./media-recent.ts";
test("previewed or uploaded files become most recent without losing earlier files", () => {
    const first = rememberMedia([], 29);
    const second = rememberMedia(first, 31);
    assert.deepEqual(second, ["31", "29"]);
    assert.deepEqual(rememberMedia(second, 29), ["29", "31"]);
    assert.deepEqual(first, ["29"]);
});
test("incomplete chunks and malformed upload identifiers add no phantom file", () => {
    for (const id of [undefined, null, {}, [], "", "undefined", 0, -1, 2.5]) {
        assert.deepEqual(rememberMedia(["29"], id), ["29"]);
    }
});
test("malformed stored data is normalized before requesting Recent", () => {
    assert.deepEqual(rememberMedia({ bad: true }, 31), ["31"]);
    assert.deepEqual(rememberMedia([29, "29", null, false, {}, "31"]), [
        "29",
        "31",
    ]);
});
test("Recent retains at most forty unique files and moves existing files to the front", () => {
    const current = Array.from({ length: 40 }, (_, i) => String(i + 1));
    const next = rememberMedia(current, 41);
    assert.equal(next.length, 40);
    assert.equal(next[0], "41");
    assert.equal(next.at(-1), "39");
    assert.deepEqual(rememberMedia(["1", "2", "1"], "2"), ["2", "1"]);
});
