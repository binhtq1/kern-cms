import test from "node:test";
import assert from "node:assert/strict";
import { cleanRichText } from "./rich-text-safety.ts";
import { validImageSource } from "./image-source.ts";
test("reply history keeps paragraphs, lists, safe links and images", () => {
    const html =
        '<p><strong>Hello</strong> <a href="https://example.com/docs">docs</a></p><ul><li>Item</li></ul><img src="/storage/one.jpg" alt="One">';
    const clean = cleanRichText(html);
    assert.match(clean, /<strong>Hello<\/strong>/);
    assert.match(clean, /href="https:\/\/example.com\/docs"/);
    assert.match(clean, /<ul><li>Item<\/li><\/ul>/);
    assert.match(clean, /src="\/storage\/one.jpg"/);
});
test("reply history removes scripts, event handlers, unsafe links and embedded documents", () => {
    const clean = cleanRichText(
        '<script>alert(1)</script><p onclick="bad()">Hi</p><a href="javascript:alert(1)">bad</a><img src="data:image/svg+xml,bad" onerror="bad()"><iframe src="https://example.com"></iframe><svg><foreignObject>bad</foreignObject></svg>',
    );
    assert.doesNotMatch(
        clean,
        /script|onclick|onerror|javascript:|data:|iframe|svg|foreignObject/i,
    );
    assert.match(clean, /<p>Hi<\/p>/);
});
test("image insertion permits only explicit safe web URLs or root-relative sources", () => {
    assert.equal(validImageSource("/storage/image.jpg"), "/storage/image.jpg");
    assert.equal(
        validImageSource("https://example.com/photo.jpg"),
        "https://example.com/photo.jpg",
    );
    for (const value of [
        "javascript:alert(1)",
        "data:image/svg+xml,test",
        "//example.com/a.jpg",
        "https://user:secret@example.com/a",
        "\\evil.test/a",
        "image.jpg",
        "",
        "/a\nb",
    ])
        assert.equal(validImageSource(value), null);
});
test("formatted replies keep safe alignment/font/color but reject executable or external styles", () => {
    const clean = cleanRichText(
        '<h1 style="text-align:center">Title</h1><p><span style="font-family:Arial;font-size:18px;color:#123456;background-image:url(https://evil.test/x);position:fixed">Safe</span></p><img src="javascript:alert(1)" onerror="bad()"><p style="color:expression(alert(1))">Bad</p>',
    );
    assert.match(clean, /<h1 style="text-align:center">/);
    assert.match(clean, /font-family:Arial/);
    assert.match(clean, /font-size:18px/);
    assert.match(clean, /color:#123456/);
    assert.doesNotMatch(
        clean,
        /background-image|evil.test|position:fixed|expression|javascript|onerror/,
    );
});
