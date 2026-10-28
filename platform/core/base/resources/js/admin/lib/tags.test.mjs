import test from "node:test";
import assert from "node:assert/strict";
import { parseTags, tagSuggestions, completeTag } from "./tags.ts";

test("comma payload trims blanks and deduplicates while retaining first spelling", () => {
    assert.deepEqual(parseTags(" Laravel, , React, laravel , React, mới "), [
        "Laravel",
        "React",
        "mới",
    ]);
    assert.deepEqual(parseTags(" , , "), []);
});
test("suggestions search current token and exclude already selected tags", () => {
    assert.deepEqual(
        tagSuggestions("Laravel, re", [
            "Laravel",
            "React",
            "Redux",
            "REACT",
            "Vue",
        ]),
        ["React", "Redux"],
    );
    assert.deepEqual(tagSuggestions("Laravel, ", ["Laravel", "Vue"]), ["Vue"]);
});
test("accepting suggestion replaces incomplete token without duplicating selected tags", () => {
    assert.equal(completeTag("Laravel, re", "React"), "Laravel, React, ");
    assert.equal(completeTag("Laravel, lar", "laravel"), "Laravel, ");
    assert.equal(completeTag("", "New tag"), "New tag, ");
});
test("manual new tag and Unicode tags share the same submission normalization", () => {
    const value = completeTag("React, bài", "bài viết");
    assert.deepEqual(
        parseTags(value).map((value) => ({ value })),
        [{ value: "React" }, { value: "bài viết" }],
    );
});
test("large option lists are bounded without excluding matches beyond first page", () => {
    const options = Array.from({ length: 100 }, (_, index) => `Tag ${index}`);
    assert.equal(tagSuggestions("", options).length, 20);
    assert.deepEqual(tagSuggestions("Tag 99", options), ["Tag 99"]);
});
