import test from "node:test";
import assert from "node:assert/strict";
import { createElement as h, Fragment } from "react";
import { selectOptions, emptyOptionValue } from "./select-options.ts";

test("selection options preserve empty labels, numeric zero and string false", () => {
    const children = [
        h("option", { key: "all", value: "" }, "All statuses"),
        h("option", { key: "zero", value: 0 }, "Top level"),
        h("option", { key: "false", value: "false" }, "Disabled"),
    ];

    const options = selectOptions(children);

    assert.deepEqual(options, [
        {
            value: "",
            label: "All statuses",
            disabled: undefined,
            group: undefined,
        },
        {
            value: "0",
            label: "Top level",
            disabled: undefined,
            group: undefined,
        },
        {
            value: "false",
            label: "Disabled",
            disabled: undefined,
            group: undefined,
        },
    ]);
});

test("fragments and optgroups retain labels and inherited disabled options", () => {
    const children = h(
        Fragment,
        null,
        h(
            "optgroup",
            { label: "Unavailable", disabled: true },
            h("option", { value: "private" }, "Private"),
        ),
        h(
            "optgroup",
            { label: "Visible" },
            h("option", { value: "public" }, "Public"),
            h("option", { value: "draft", disabled: true }, "Draft"),
        ),
    );

    const options = selectOptions(children);

    assert.deepEqual(options, [
        {
            value: "private",
            label: "Private",
            disabled: true,
            group: "Unavailable",
        },
        {
            value: "public",
            label: "Public",
            disabled: undefined,
            group: "Visible",
        },
        { value: "draft", label: "Draft", disabled: true, group: "Visible" },
    ]);
});

test("empty-option sentinel cannot shadow a real stored value", () => {
    const options = selectOptions([
        h("option", { key: "empty", value: "" }, "None"),
        h("option", { key: "seed", value: "seed" }, "Seed"),
        h("option", { key: "collision", value: "seed_" }, "Collision"),
    ]);

    assert.equal(emptyOptionValue(options, "seed"), "seed__");
    assert.equal(emptyOptionValue(options, "another"), "another");
});
