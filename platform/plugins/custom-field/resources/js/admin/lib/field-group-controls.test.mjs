import test from "node:test";
import assert from "node:assert/strict";
import { newItem, typeLabels } from "./field-group-controls.ts";

test("new field items have a complete default shape and independent nested state", () => {
    const first = newItem();
    const second = newItem();

    assert.deepEqual(first, {
        id: null,
        title: "",
        slug: "",
        type: "text",
        instructions: "",
        options: {},
        items: [],
    });
    assert.deepEqual(second, first);
    assert.notStrictEqual(first.options, second.options);
    assert.notStrictEqual(first.items, second.items);

    first.options.placeholder = "For this field only";
    first.items.push(newItem());
    assert.deepEqual(second.options, {});
    assert.deepEqual(second.items, []);
});

test("type labels cover every supported field type without mutating type choices", () => {
    const expectedTypes = [
        "text",
        "textarea",
        "number",
        "email",
        "password",
        "wysiwyg",
        "image",
        "file",
        "select",
        "checkbox",
        "radio",
        "repeater",
        "date",
        "time",
        "color",
    ];
    assert.deepEqual(Object.keys(typeLabels), expectedTypes);
    assert.equal(typeLabels.text, "Text field");
    assert.equal(typeLabels.repeater, "Repeater");
});
