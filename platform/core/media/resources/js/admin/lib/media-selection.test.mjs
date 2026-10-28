import test from "node:test";
import assert from "node:assert/strict";
import {
    mediaItemKey,
    selectMediaItems,
    toggleMediaItem,
} from "./media-selection.ts";

const file = (id, name = `file-${id}`) => ({ id, name, is_folder: false });
const folder = (id, name = `folder-${id}`) => ({ id, name, is_folder: true });

test("file and folder IDs remain distinct in keys and selection toggles", () => {
    const sameIdFile = file("7");
    const sameIdFolder = folder("7");
    assert.notEqual(mediaItemKey(sameIdFile), mediaItemKey(sameIdFolder));
    assert.deepEqual(toggleMediaItem([sameIdFile], sameIdFolder), [
        sameIdFile,
        sameIdFolder,
    ]);
    assert.deepEqual(toggleMediaItem([sameIdFile, sameIdFolder], sameIdFile), [
        sameIdFolder,
    ]);
});

test("ordinary folder activation opens it without replacing selected items or anchor", () => {
    const selected = [file(1), folder(2)];
    const anchor = mediaItemKey(selected[0]);
    const result = selectMediaItems(
        [folder(3), file(4)],
        selected,
        folder(3),
        {},
        anchor,
    );

    assert.deepEqual(result, { selected, anchor, openFolder: true });
    assert.strictEqual(
        result.selected,
        selected,
        "folder navigation keeps selection identity untouched",
    );
});

test("shift selection includes an inclusive range in either direction and keeps the original anchor", () => {
    const items = [file("a"), folder("b"), file("c"), file("d")];
    const anchor = mediaItemKey(items[2]);
    const backwards = selectMediaItems(
        items,
        [items[0]],
        items[0],
        { shiftKey: true },
        anchor,
    );
    assert.deepEqual(backwards.selected, items.slice(0, 3));
    assert.equal(backwards.anchor, anchor);
    assert.equal(backwards.openFolder, false);

    const forwards = selectMediaItems(
        items,
        [],
        items[3],
        { shiftKey: true },
        mediaItemKey(items[0]),
    );
    assert.deepEqual(forwards.selected, items);
    assert.equal(forwards.anchor, mediaItemKey(items[0]));
});

test("control/meta toggle individual entries while plain file selection resets the range", () => {
    const items = [file(1), file(2), file(3)];
    const selected = [items[0], items[1]];
    const ctrl = selectMediaItems(
        items,
        selected,
        items[0],
        { ctrlKey: true },
        mediaItemKey(items[1]),
    );
    assert.deepEqual(ctrl.selected, [items[1]]);
    assert.equal(ctrl.anchor, mediaItemKey(items[1]));
    assert.equal(ctrl.openFolder, false);

    const meta = selectMediaItems(
        items,
        selected,
        items[2],
        { metaKey: true },
        null,
    );
    assert.deepEqual(meta.selected, [...selected, items[2]]);
    assert.equal(
        meta.anchor,
        null,
        "modifier toggles do not disturb the range anchor",
    );

    const plain = selectMediaItems(
        items,
        selected,
        items[2],
        {},
        mediaItemKey(items[0]),
    );
    assert.deepEqual(plain.selected, [items[2]]);
    assert.equal(plain.anchor, mediaItemKey(items[2]));
});

test("modified folder selection does not navigate into the folder", () => {
    const items = [file(1), folder(2), file(3)];
    const selected = [items[0]];
    const ctrl = selectMediaItems(
        items,
        selected,
        items[1],
        { ctrlKey: true },
        null,
    );
    assert.deepEqual(ctrl.selected, [...selected, items[1]]);
    assert.equal(ctrl.openFolder, false);

    const shifted = selectMediaItems(
        items,
        [],
        items[1],
        { shiftKey: true },
        mediaItemKey(items[0]),
    );
    assert.deepEqual(shifted.selected, [items[0], items[1]]);
    assert.equal(shifted.openFolder, false);
});
