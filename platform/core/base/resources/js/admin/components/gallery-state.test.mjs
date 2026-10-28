import test from "node:test";
import assert from "node:assert/strict";
import { reorderGallery } from "./gallery-state.ts";
test("drag and keyboard gallery reorder move one image and preserve descriptions", () => {
    const images = [
        { img: "a", description: "A" },
        { img: "b", description: "B" },
        { img: "c", description: "C" },
    ];
    assert.deepEqual(reorderGallery(images, 0, 2), [
        images[1],
        images[2],
        images[0],
    ]);
    assert.deepEqual(reorderGallery(images, 2, 0), [
        images[2],
        images[0],
        images[1],
    ]);
    assert.deepEqual(
        images.map((image) => image.img),
        ["a", "b", "c"],
    );
    for (const [from, to] of [
        [-1, 1],
        [1, 3],
        [0, 0],
    ])
        assert.equal(reorderGallery(images, from, to), images);
});
