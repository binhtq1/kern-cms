import test from "node:test";
import assert from "node:assert/strict";
import {
    textMatches,
    validMediaUrl,
    validDimension,
    clampIndent,
} from "./rich-editor-tools.ts";

test("find and replace uses literal non-overlapping matches and honors case", () => {
    assert.deepEqual(textMatches("CMS cms CMS", "cms"), [0, 4, 8]);
    assert.deepEqual(textMatches("CMS cms CMS", "cms", true), [4]);
    assert.deepEqual(textMatches("[block] [block]", "[block]"), [0, 8]);
    assert.deepEqual(textMatches("aaa", "aa"), [0]);
    assert.deepEqual(textMatches("text", ""), []);
    assert.deepEqual(textMatches("İ CMS", "CMS"), [2]);
});
test("media embeds reject executable and protocol-relative URLs", () => {
    for (const value of [
        "https://player.vimeo.com/video/1",
        "/storage/audio.mp3",
    ])
        assert.equal(validMediaUrl(value), true);
    for (const value of [
        "javascript:alert(1)",
        "data:text/html,Hi",
        "//evil.test",
        'https://x.test/\" onload=\"alert(1)',
    ])
        assert.equal(validMediaUrl(value), false);
});
test("image and table dimensions cannot introduce CSS and indentation remains bounded", () => {
    for (const value of ["", "100", "50%", "320px"])
        assert.equal(validDimension(value), true);
    for (const value of ["10px;display:none", "-1", "calc(1px)"])
        assert.equal(validDimension(value), false);
    assert.equal(clampIndent(-1), 0);
    assert.equal(clampIndent(9), 8);
});
