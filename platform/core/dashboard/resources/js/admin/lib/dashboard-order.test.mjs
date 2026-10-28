import test from "node:test";
import assert from "node:assert/strict";
import { reorderDashboardWidgets } from "./dashboard-order.ts";

const widget = (name, visible = true, type = "widget") => ({
    id: name,
    name,
    title: name,
    url: `/dashboard/${name}`,
    type,
    visible,
});

test("keyboard/default ordering includes hidden normal widgets and excludes stats", () => {
    const display = [
        widget("first"),
        widget("hidden", false),
        widget("visitors", true, "stats"),
        widget("last"),
        widget("pageviews", true, "stats"),
    ];
    const before = structuredClone(display);
    const result = reorderDashboardWidgets(display, "first", "last");

    assert.deepEqual(result.names, ["hidden", "last", "first"]);
    assert.deepEqual(result.display, [
        display[2],
        display[4],
        display[1],
        display[3],
        display[0],
    ]);
    assert.deepEqual(
        display,
        before,
        "reordering must not mutate incoming props",
    );
    assert.deepEqual(
        result.display.slice(0, 2),
        [display[2], display[4]],
        "stats partition and order are retained",
    );
});

test("pointer ordering supports before and after targets in both directions", () => {
    const display = [widget("a"), widget("b"), widget("c"), widget("d", false)];

    assert.deepEqual(reorderDashboardWidgets(display, "a", "c", false).names, [
        "b",
        "a",
        "c",
        "d",
    ]);
    assert.deepEqual(reorderDashboardWidgets(display, "a", "c", true).names, [
        "b",
        "c",
        "a",
        "d",
    ]);
    assert.deepEqual(reorderDashboardWidgets(display, "d", "b", false).names, [
        "a",
        "d",
        "b",
        "c",
    ]);
    assert.deepEqual(reorderDashboardWidgets(display, "d", "b", true).names, [
        "a",
        "b",
        "d",
        "c",
    ]);
});

test("invalid and same-target drops are no-ops", () => {
    const display = [widget("a"), widget("b")];
    assert.equal(reorderDashboardWidgets(display, "a", "a"), null);
    assert.equal(reorderDashboardWidgets(display, "missing", "b"), null);
    assert.equal(reorderDashboardWidgets(display, "a", "missing"), null);
    assert.deepEqual(
        display.map((item) => item.name),
        ["a", "b"],
    );
});
