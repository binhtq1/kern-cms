import test from "node:test";
import assert from "node:assert/strict";
import { selectMenuReferences, referenceMenuItems } from "./item-edit.ts";
import { cancelMenuItem, serializeMenu } from "./tree.ts";
const reference = (value, type = "Page") => ({
    value,
    label: `${type} ${value}`,
    type,
    id: value,
    title: value,
    url: `/${value}`,
});
const node = (key, parent = "") => ({
    key,
    parent,
    title: key,
    url: `/${key}`,
    reference_type: "",
    reference_id: "",
    icon_font: "",
    icon_image: "",
    css_class: "",
    target: "_self",
});
test("reference selection retains metadata across searches and pages, drops unknown IDs and unselected values", () => {
    const page1 = [reference("1"), reference("2")];
    const selected = selectMenuReferences([], page1, ["2", "1"]);
    const page2 = [reference("3")];
    assert.deepEqual(
        selectMenuReferences(selected, page2, ["2", "1", "3"]).map((r) => r.id),
        ["2", "1", "3"],
    );
    assert.deepEqual(
        selectMenuReferences(selected, page2, ["3", "unknown", "1"]).map(
            (r) => r.id,
        ),
        ["3", "1"],
    );
    assert.deepEqual(
        selected.map((r) => r.id),
        ["2", "1"],
    );
});
test("batch add preserves selection order and deduplicates only within one batch", () => {
    let key = 0;
    const refs = [reference("2"), reference("1"), reference("2")];
    const added = referenceMenuItems(refs, () => `new-${++key}`, true);
    assert.deepEqual(
        added.map((n) => [n.key, n.reference_id, n.parent, n.icon_image]),
        [
            ["new-1", "2", "", ""],
            ["new-2", "1", "", ""],
        ],
    );
    assert.deepEqual(
        referenceMenuItems([reference("2")], () => "another", false).map(
            (n) => n.reference_id,
        ),
        ["2"],
    );
    assert.equal(
        "icon_image" in
            referenceMenuItems([reference("1")], () => "other", false)[0],
        false,
    );
});
test("different plugin references with the same numeric ID remain distinct", () => {
    const refs = [reference("Page|1", "Page"), reference("Tag|1", "Tag")];
    let key = 0;
    assert.equal(
        referenceMenuItems(refs, () => `key-${++key}`, false).length,
        2,
    );
});
test("Cancel restores every editable field and old parent while retaining ID and descendants", () => {
    const snapshot = {
        ...node("a", "root"),
        id: "saved-id",
        title: "Before",
        url: "/before",
        reference_type: "Page",
        reference_id: "12",
        icon_font: "ti ti-link",
        icon_image: "before.png",
        css_class: "before",
        target: "_blank",
    };
    const changed = {
        ...snapshot,
        parent: "other",
        title: "After",
        url: "/after",
        reference_type: "",
        reference_id: "",
        icon_font: "",
        icon_image: "after.png",
        css_class: "after",
        target: "_self",
    };
    const child = node("child", "a");
    const items = [node("root"), node("other"), changed, child];
    const restored = cancelMenuItem(items, "a", snapshot);
    assert.deepEqual(
        restored.find((n) => n.key === "a"),
        snapshot,
    );
    assert.equal(
        restored.find((n) => n.key === "child"),
        child,
    );
    assert.equal(items[2], changed);
    assert.doesNotThrow(() => serializeMenu(restored));
});
test("new node Cancel retains the node and its children instead of deleting it", () => {
    const snapshot = node("new");
    const child = node("child", "new");
    const restored = cancelMenuItem(
        [{ ...snapshot, title: "Draft" }, child],
        "new",
        snapshot,
    );
    assert.equal(restored.length, 2);
    assert.equal(restored.find((n) => n.key === "new").title, "new");
    assert.equal(restored.find((n) => n.key === "child").parent, "new");
});
test("Cancel after save preserves the newly allocated persistent ID", () => {
    const before = node("draft");
    const restored = cancelMenuItem(
        [{ ...before, id: "42", title: "Changed" }],
        "draft",
        before,
    );
    assert.equal(restored[0].id, "42");
    assert.equal(restored[0].key, "draft");
});
test("Cancel cannot restore a missing or descendant parent into an invalid tree", () => {
    for (const parent of ["missing", "child"]) {
        const items = [node("a"), node("child", "a")];
        const restored = cancelMenuItem(items, "a", {
            ...node("a", parent),
            title: "Before",
        });
        assert.equal(restored.find((n) => n.key === "a").parent, "");
        assert.equal(restored.find((n) => n.key === "child").parent, "a");
        assert.doesNotThrow(() => serializeMenu(restored));
    }
});

test("Cancel uses a safe root when a formerly valid parent would now exceed maximum depth", () => {
    const items = [
        node("one"),
        node("two", "one"),
        node("three", "two"),
        node("four", "three"),
        node("five", "four"),
        node("a"),
        node("child", "a"),
    ];
    const restored = cancelMenuItem(items, "a", {
        ...node("a", "five"),
        title: "Before",
    });
    assert.equal(restored.find((n) => n.key === "a").parent, "");
    assert.doesNotThrow(() => serializeMenu(restored));
});

test("Cancel of field edits does not reorder untouched siblings or descendants", () => {
    const original = node("a");
    const items = [
        { ...original, title: "Draft" },
        node("child", "a"),
        node("b"),
        node("c"),
    ];
    const restored = cancelMenuItem(items, "a", original);
    assert.deepEqual(
        restored.map((item) => item.key),
        ["a", "child", "b", "c"],
    );
});
