import test from "node:test";
import assert from "node:assert/strict";
import { editorColorHex } from "./rich-editor-format.ts";

test("color controls display parsed CSS rgb and rgba values as hex", () => {
    assert.equal(editorColorHex("rgb(255, 0, 128)", "#000000"), "#ff0080");
    assert.equal(editorColorHex("rgba(36, 84, 67, 0.5)", "#000000"), "#245443");
    assert.equal(editorColorHex("rgb(100% 0% 50%)", "#000000"), "#ff0080");
    assert.equal(editorColorHex("rgb(36 84 67 / 50%)", "#000000"), "#245443");
});

test("color controls retain hex, common named colors and a safe absent-style default", () => {
    assert.equal(editorColorHex("#AABBCC", "#000000"), "#aabbcc");
    assert.equal(editorColorHex("#ABC", "#000000"), "#aabbcc");
    assert.equal(editorColorHex("red", "#000000"), "#ff0000");
    for (const value of [undefined, null, "", "transparent", "invalid"]) {
        assert.equal(editorColorHex(value, "#ffffff"), "#ffffff");
    }
});
