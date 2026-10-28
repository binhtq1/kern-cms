import test from "node:test";
import assert from "node:assert/strict";
import { parseJsonImport } from "./json-import.ts";

test("JSON imports accept exported arrays and enforce UTF-8 bytes and row limits for files or pasted content", () => {
    assert.deepEqual(parseJsonImport('[{"title":"Example"}]', 100, 2), [
        { title: "Example" },
    ]);
    assert.throws(() => parseJsonImport('["ệ"]', 6, 2), /large/);
    assert.throws(() => parseJsonImport("[1,2,3]", 100, 2), /Invalid/);
    assert.throws(() => parseJsonImport("[]", 100, 2), /Invalid/);
    assert.throws(() => parseJsonImport("{}", 100, 2), /Invalid/);
    assert.throws(() => parseJsonImport("invalid", 100, 2));
});
