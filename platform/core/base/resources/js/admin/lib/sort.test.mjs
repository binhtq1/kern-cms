import test from "node:test";
import assert from "node:assert/strict";
import { moveGroupedItem, sortDestination } from "./sort.ts";

test("drop position distinguishes before, after and nesting without requiring a tiny target", () => {
    const rect = { left: 100, top: 100, height: 48 };
    assert.equal(
        sortDestination(rect, 180, 104, "before", "after", "inside"),
        "before",
    );
    assert.equal(
        sortDestination(rect, 180, 124, "before", "after", "inside"),
        "inside",
    );
    assert.equal(
        sortDestination(rect, 120, 124, "before", "after", "inside"),
        "after",
    );
    assert.equal(
        sortDestination(rect, 180, 145, "before", "after", "inside"),
        "after",
    );
    assert.equal(sortDestination(rect, 180, 124, "before", "after"), "after");
});
const a = { key: "a", data: { title: "Keep this content" } };
const b = { key: "b", data: { title: "Second" } };
const c = { key: "c", data: { title: "Third" } };
const groups = [
    { id: "sidebar", items: [a, b] },
    { id: "footer", items: [c] },
    { id: "empty", items: [] },
];
test("widgets move across areas and into empty areas without loss or duplication", () => {
    const moved = moveGroupedItem(groups, "a", "footer", "c");
    assert.deepEqual(
        moved.map((group) => group.items.map((item) => item.key)),
        [["b"], ["a", "c"], []],
    );
    assert.equal(moved[1].items[0], a);
    assert.deepEqual(
        moveGroupedItem(moved, "a", "empty").map((group) =>
            group.items.map((item) => item.key),
        ),
        [["b"], ["c"], ["a"]],
    );
    assert.deepEqual(groups[0].items, [a, b]);
});
test("same-area moves account for the removed source and invalid drops preserve state", () => {
    assert.deepEqual(moveGroupedItem(groups, "a", "sidebar")[0].items, [b, a]);
    assert.deepEqual(moveGroupedItem(groups, "b", "sidebar", "a")[0].items, [
        b,
        a,
    ]);
    assert.equal(moveGroupedItem(groups, "a", "missing"), groups);
    assert.equal(moveGroupedItem(groups, "missing", "sidebar"), groups);
    assert.equal(moveGroupedItem(groups, "a", "sidebar", "a"), groups);
});
test("adding a registry widget preserves existing instances and inserts at the requested position", () => {
    const created = { key: "fresh", data: { title: "New widget" } };
    assert.deepEqual(
        moveGroupedItem(groups, "new:Text", "sidebar", "b", created)[0].items,
        [a, created, b],
    );
    assert.equal(groups[0].items.length, 2);
});
