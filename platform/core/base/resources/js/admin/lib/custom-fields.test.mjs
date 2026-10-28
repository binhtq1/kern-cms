import test from "node:test";
import assert from "node:assert/strict";
import { initializeCustomFields } from "./custom-fields.ts";

const field = (id, type, value) => ({
    id,
    title: id,
    slug: id,
    type,
    value,
    options: {
        defaultValue: "Default",
        defaultValueTextarea: "<p>Default rich text</p>",
    },
});

test("saved empty and zero values survive initialization while missing values adopt matching defaults", () => {
    const groups = [
        {
            id: 1,
            title: "Fields",
            items: [
                field("empty", "wysiwyg", ""),
                field("zero", "number", "0"),
                field("missing", "textarea"),
                field("text", "text", null),
            ],
        },
    ];
    const [initialized] = initializeCustomFields(groups);
    assert.deepEqual(
        initialized.items.map((item) => item.value),
        ["", "0", "<p>Default rich text</p>", "Default"],
    );
    assert.equal(groups[0].items[2].value, undefined);
});

test("cleared rich text stays empty through a subsequent form initialization including repeaters", () => {
    const groups = [
        {
            id: 1,
            title: "Fields",
            items: [
                {
                    ...field("rows", "repeater"),
                    value: [[field("rich", "wysiwyg", "")]],
                    items: [field("rich", "wysiwyg")],
                },
                field("checks", "checkbox"),
            ],
        },
    ];
    const [initialized] = initializeCustomFields(groups);
    assert.equal(initialized.items[0].value[0][0].value, "");
    assert.equal(
        initialized.items[0].items[0].value,
        "<p>Default rich text</p>",
    );
    assert.deepEqual(initialized.items[1].value, []);
    assert.notEqual(initialized.items[0].value[0], groups[0].items[0].value[0]);
});
