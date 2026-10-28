import test from "node:test";
import assert from "node:assert/strict";
import {
    calendarDays,
    localDateValue,
    withinBounds,
    timeFromDraft,
} from "./date-time.ts";

test("calendar includes leap day only in leap years", () => {
    assert.ok(calendarDays(2024, 1).includes("2024-02-29"));
    assert.ok(!calendarDays(2023, 1).includes("2023-02-29"));
    assert.ok(!calendarDays(2100, 1).includes("2100-02-29"));
    assert.ok(calendarDays(2000, 1).includes("2000-02-29"));
});
test("calendar day alignment and month lengths remain correct across years", () => {
    assert.equal(
        calendarDays(2026, 0)
            .slice(0, 4)
            .every((date) => date === null),
        true,
    );
    assert.equal(calendarDays(2026, 0).at(-1), "2026-01-31");
    assert.equal(calendarDays(2025, 11).at(-1), "2025-12-31");
    assert.equal(calendarDays(2026, 3).at(-1), "2026-04-30");
});
test("date and time boundaries are inclusive without timezone conversion", () => {
    assert.ok(withinBounds("2026-10-16", "2026-10-16", "2026-10-16"));
    assert.ok(!withinBounds("2026-10-15", "2026-10-16"));
    assert.ok(withinBounds("09:00", "09:00", "17:00"));
    assert.ok(!withinBounds("17:01", "09:00", "17:00"));
    assert.ok(!withinBounds("2026-10-16T08:59", "2026-10-16T09:00"));
    assert.equal(localDateValue(new Date(2026, 9, 16, 0, 1)), "2026-10-16");
});

test("empty time drafts start at midnight and existing seconds stay intact", () => {
    assert.equal(timeFromDraft("", "time"), "00:00");
    assert.equal(timeFromDraft("2026-10-16", "datetime-local"), "00:00");
    assert.equal(
        timeFromDraft("2026-10-16T14:25:30", "datetime-local"),
        "14:25:30",
    );
    assert.equal(timeFromDraft("07:05:45", "time"), "07:05:45");
});
