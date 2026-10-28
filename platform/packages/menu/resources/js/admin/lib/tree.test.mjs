import test from "node:test";
import assert from "node:assert/strict";
import {
    descendants,
    reorderMenu,
    serializeMenu,
    moveMenuItem,
} from "./tree.ts";

const item = (key, parent = "") => ({
    key,
    parent,
    id: key,
    title: key,
    url: "/",
    reference_type: "",
    reference_id: "",
    icon_font: "",
    css_class: "",
    target: "_self",
});

test("menu serialization preserves nested parents and ordered siblings without client keys", () => {
    const tree = serializeMenu([item("1"), item("2", "1"), item("3")]);
    assert.equal(tree[0].children[0].menuItem.id, "2");
    assert.equal(tree[1].menuItem.position, 1);
    assert.equal("key" in tree[0].menuItem, false);
});

test("keyboard reorder stays inside the parent and preserves descendants", () => {
    const items = [item("1"), item("2", "1"), item("3"), item("4", "1")];
    const moved = reorderMenu(items, "4", -1);
    assert.deepEqual(
        serializeMenu(moved)[0].children.map((node) => node.menuItem.id),
        ["4", "2"],
    );
    assert.deepEqual([...descendants(items, "1")], ["1", "2", "4"]);
    assert.deepEqual(reorderMenu(items, "1", -1), items);
});

test("moving a menu branch between parents preserves its children and sibling order", () => {
    const items = [item("1"), item("2", "1"), item("3"), item("4", "3")];
    const moved = moveMenuItem(items, "3", "1", "2");
    const tree = serializeMenu(moved);
    assert.deepEqual(
        tree[0].children.map((node) => node.menuItem.id),
        ["3", "2"],
    );
    assert.equal(tree[0].children[0].children[0].menuItem.id, "4");
    assert.equal(items[2].parent, "");
    const unnested = serializeMenu(moveMenuItem(moved, "3", "", "1"));
    assert.deepEqual(
        unnested.map((node) => node.menuItem.id),
        ["3", "1"],
    );
    assert.equal(unnested[0].children[0].menuItem.id, "4");
});

test("menu drag moves reject cycles and excessive nesting before changing the tree", () => {
    const items = [
        item("1"),
        item("2", "1"),
        item("3", "2"),
        item("4", "3"),
        item("5", "4"),
        item("6"),
        item("7", "6"),
    ];
    assert.throws(() => moveMenuItem(items, "1", "5"), /descendants/);
    assert.throws(() => moveMenuItem(items, "6", "5"), /five levels/);
    assert.equal(items[5].parent, "");
    assert.equal(serializeMenu(items).length, 2);
});
