import test from "node:test";
import assert from "node:assert/strict";
import { reconcileMenuSave, mergeSavedFields } from "./save-state.ts";
const item = (key, parent = "", id) => ({
    key,
    parent,
    ...(id ? { id } : {}),
    title: key,
    url: "/same-url",
    reference_type: "",
    reference_id: "",
    icon_font: "",
    css_class: "",
    target: "_self",
});
test("menu save reconciles newly allocated IDs by submitted tree position, retaining duplicate links and later edits", () => {
    const submitted = [item("a"), item("child", "a"), item("b")];
    const saved = [
        item("11", "", "11"),
        item("12", "11", "12"),
        item("13", "", "13"),
    ];
    const current = [
        { ...submitted[2], title: "Unsent title" },
        submitted[0],
        item("new-draft"),
        { ...submitted[1], parent: "b" },
    ];
    const result = reconcileMenuSave(submitted, saved, current);
    assert.deepEqual(
        result.baseline.map((entry) => [entry.key, entry.id]),
        [
            ["a", "11"],
            ["child", "12"],
            ["b", "13"],
        ],
    );
    assert.deepEqual(
        result.current.map((entry) => [entry.key, entry.id, entry.parent]),
        [
            ["b", "13", ""],
            ["a", "11", ""],
            ["new-draft", undefined, ""],
            ["child", "12", "b"],
        ],
    );
    assert.equal(result.current[0].title, "Unsent title");
    assert.notDeepEqual(result.current, result.baseline);
    assert.equal(submitted[0].id, undefined);
});
test("menu save does not resurrect nodes removed while the request was pending", () => {
    const result = reconcileMenuSave(
        [item("a"), item("b")],
        [item("11", "", "11"), item("12", "", "12")],
        [item("b")],
    );
    assert.deepEqual(
        result.current.map((entry) => entry.key),
        ["b"],
    );
    assert.equal(result.current[0].id, "12");
});
test("saved form normalization updates unchanged fields and preserves newer name/status/location drafts", () => {
    const submitted = {
        name: "Menu",
        status: "published",
        locations: ["main"],
        ref_from: "1",
    };
    const saved = { ...submitted, name: "Saved menu", ref_from: "" };
    const current = {
        ...submitted,
        name: "Unsent menu",
        status: "draft",
        locations: ["footer"],
    };
    assert.deepEqual(mergeSavedFields(submitted, saved, current), {
        ...current,
        ref_from: "",
    });
    assert.deepEqual(mergeSavedFields(submitted, saved, submitted), saved);
});
