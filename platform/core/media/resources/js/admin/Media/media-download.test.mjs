import test from "node:test";
import assert from "node:assert/strict";
import { mediaDownloadFilename } from "./media-download.ts";
const file = {
    name: "9",
    is_folder: false,
    full_url: "https://example.test/news/9.jpg?x=1",
    mime_type: "image/jpeg",
};
test("download prefers authoritative UTF-8 and quoted Content-Disposition names", () => {
    assert.equal(
        mediaDownloadFilename(
            "attachment; filename=old.jpg; filename*=UTF-8''%E1%BA%A3nh.jpg",
            [file],
            "image/jpeg",
        ),
        "ảnh.jpg",
    );
    assert.equal(
        mediaDownloadFilename(
            'attachment; filename="original picture.jpg"',
            [file],
            "image/jpeg",
        ),
        "original picture.jpg",
    );
    assert.equal(
        mediaDownloadFilename(
            "attachment; filename*=UTF-8''%ZZ; filename=fallback.jpg",
            [file],
            "image/jpeg",
        ),
        "fallback.jpg",
    );
});
test("download preserves file extensions and gives single folders and selections ZIP names", () => {
    assert.equal(mediaDownloadFilename(null, [file], "image/jpeg"), "9.jpg");
    assert.equal(
        mediaDownloadFilename(
            null,
            [{ ...file, name: "Image.jpeg" }],
            "image/jpeg",
        ),
        "Image.jpeg",
    );
    assert.equal(
        mediaDownloadFilename(
            null,
            [{ ...file, name: "News", is_folder: true }],
            "application/octet-stream",
        ),
        "News.zip",
    );
    assert.equal(
        mediaDownloadFilename(null, [file, file], "application/zip"),
        "media.zip",
    );
    assert.equal(
        mediaDownloadFilename(
            null,
            [{ ...file, full_url: null }],
            "image/jpeg",
        ),
        "9.jpg",
    );
});
test("download sanitizes paths, control characters and unsafe filesystem names", () => {
    assert.equal(
        mediaDownloadFilename(
            'attachment; filename="../../bad.jpg"',
            [file],
            "image/jpeg",
        ),
        "_.._bad.jpg",
    );
    assert.equal(
        mediaDownloadFilename(
            "attachment; filename*=UTF-8''a%0Ab%2Fbad.jpg",
            [file],
            "image/jpeg",
        ),
        "a_b_bad.jpg",
    );
    assert.equal(
        mediaDownloadFilename(
            'attachment; filename="..."',
            [file],
            "image/jpeg",
        ),
        "9.jpg",
    );
});
