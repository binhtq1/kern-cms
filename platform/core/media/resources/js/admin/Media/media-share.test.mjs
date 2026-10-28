import test from "node:test";
import assert from "node:assert/strict";
import { mediaShareText } from "./media-share.ts";
const image = {
    is_folder: false,
    name: "Picture",
    alt: "A photo",
    full_url: "https://example.test/photo.jpg",
    indirect_url: "/media/9",
    mime_type: "image/jpeg",
};
const document = {
    is_folder: false,
    name: "Manual.pdf",
    full_url: "https://example.test/manual.pdf",
    mime_type: "application/pdf",
};
test("copy URLs preserves selection order and skips folders or missing URLs", () => {
    assert.equal(
        mediaShareText([image, { ...image, is_folder: true }, document], "url"),
        "https://example.test/photo.jpg\nhttps://example.test/manual.pdf",
    );
    assert.equal(mediaShareText([image, document], "indirect_url"), "/media/9");
});
test("share renders images and document links with a useful name fallback", () => {
    assert.equal(
        mediaShareText([image], "html"),
        '<img src="https://example.test/photo.jpg" alt="A photo" />',
    );
    assert.equal(
        mediaShareText([document], "html"),
        '<a href="https://example.test/manual.pdf" target="_blank" rel="noopener noreferrer">Manual.pdf</a>',
    );
    assert.equal(
        mediaShareText([image, document], "markdown"),
        "![A photo](https://example.test/photo.jpg)\n[Manual.pdf](https://example.test/manual.pdf)",
    );
});
test("HTML share escapes attribute and text injection", () => {
    const result = mediaShareText(
        [
            {
                ...document,
                name: '<img onerror="bad">&',
                full_url: 'https://example.test/a?x="&y=1',
            },
        ],
        "html",
    );
    assert.equal(
        result,
        '<a href="https://example.test/a?x=&quot;&amp;y=1" target="_blank" rel="noopener noreferrer">&lt;img onerror=&quot;bad&quot;&gt;&amp;</a>',
    );
});
test("share rejects executable and control-character URLs in all formats", () => {
    for (const value of [
        "javascript:alert(1)",
        "data:text/html,bad",
        "//evil.test/a",
        "https://example.test/a\n<script>",
    ]) {
        for (const type of ["url", "indirect_url", "html", "markdown"]) {
            assert.equal(
                mediaShareText(
                    [{ ...image, full_url: value, indirect_url: value }],
                    type,
                ),
                "",
            );
        }
    }
});
test("Markdown escapes delimiters and normalizes multiline labels", () => {
    assert.equal(
        mediaShareText(
            [
                {
                    ...image,
                    alt: "[a]\n<b>",
                    full_url: "https://example.test/a (b).jpg",
                },
            ],
            "markdown",
        ),
        "![\\[a\\] \\<b\\>](https://example.test/a%20%28b%29.jpg)",
    );
});
