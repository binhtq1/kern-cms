import test from "node:test";
import assert from "node:assert/strict";
import { mediaPreviewSource } from "./image-source.ts";

test("member previews resolve relative media without changing the saved value", () => {
    const saved = "members/7/ảnh đại diện.png";
    assert.equal(
        mediaPreviewSource(
            saved,
            "https://cdn.example.test/storage/__KERN_MEDIA_PATH__",
        ),
        "https://cdn.example.test/storage/members/7/%E1%BA%A3nh%20%C4%91%E1%BA%A1i%20di%E1%BB%87n.png",
    );
    assert.equal(saved, "members/7/ảnh đại diện.png");
    assert.equal(mediaPreviewSource(saved), undefined);
    assert.equal(
        mediaPreviewSource("/storage/members/7/avatar.png"),
        "/storage/members/7/avatar.png",
    );
    assert.equal(
        mediaPreviewSource("https://cdn.example.test/avatar.png"),
        "https://cdn.example.test/avatar.png",
    );
});

test("member preview paths reject traversal and invalid source characters", () => {
    for (const value of [
        "members/../private.png",
        "./secret.png",
        "members\\private.png",
        "members/\u0000private.png",
    ]) {
        assert.equal(
            mediaPreviewSource(value, "/storage/__KERN_MEDIA_PATH__"),
            undefined,
        );
    }
    assert.equal(
        mediaPreviewSource(
            "members/avatar.png",
            "/storage/without-placeholder",
        ),
        undefined,
    );
});
