import test from "node:test";
import assert from "node:assert/strict";
import { descendants, moveNode, serializeTree } from "./tree.ts";
const nodes = () => [
    { id: "1", parent: "0", name: "Editorial" },
    { id: "2", parent: "1", name: "News" },
    { id: "3", parent: "2", name: "Local" },
    { id: "4", parent: "0", name: "Reviews" },
];

test("moving a category preserves its full descendant branch and source array", () => {
    const original = nodes();
    const moved = moveNode(original, "1", "4");
    assert.equal(original[0].parent, "0");
    assert.equal(moved.find((node) => node.id === "1").parent, "4");
    assert.equal(moved.find((node) => node.id === "2").parent, "1");
    assert.deepEqual(serializeTree(moved), [
        {
            id: "4",
            name: "Reviews",
            children: [
                {
                    id: "1",
                    name: "Editorial",
                    children: [
                        {
                            id: "2",
                            name: "News",
                            children: [
                                { id: "3", name: "Local", children: [] },
                            ],
                        },
                    ],
                },
            ],
        },
    ]);
});

test("before/after reorder changes sibling order while outdent changes parent", () => {
    const moved = moveNode(nodes(), "4", "0", "1");
    assert.deepEqual(
        serializeTree(moved).map((node) => node.id),
        ["4", "1"],
    );
    const outdented = moveNode(moved, "2", "0");
    assert.deepEqual(
        serializeTree(outdented).map((node) => node.id),
        ["4", "1", "2"],
    );
    assert.equal(outdented.find((node) => node.id === "3").parent, "2");
});

test("a category cannot move into itself or any descendant", () => {
    assert.deepEqual([...descendants(nodes(), "1")], ["1", "2", "3"]);
    assert.throws(() => moveNode(nodes(), "1", "3"), /descendants/);
    assert.throws(() => moveNode(nodes(), "2", "2"), /descendants/);
});

test("moving a branch cannot create more than five levels", () => {
    const deep = [
        ...nodes(),
        { id: "5", parent: "3", name: "Fourth" },
        { id: "6", parent: "5", name: "Fifth" },
    ];
    assert.throws(() => moveNode(deep, "4", "6"), /five levels/);
    assert.equal(deep.find((node) => node.id === "4").parent, "0");
});

test("serialization rejects missing parents and cycles rather than silently omitting nodes", () => {
    assert.throws(
        () => serializeTree([{ id: "1", parent: "99", name: "Orphan" }]),
        /missing parent/,
    );
    assert.throws(
        () =>
            serializeTree([
                { id: "1", parent: "2", name: "A" },
                { id: "2", parent: "1", name: "B" },
            ]),
        /cycle/,
    );
});
