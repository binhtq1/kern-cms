import { safeConsoleLink } from "./console-link.ts";
import { assertAdminResult } from "./admin-result.ts";
import { pageSizeOptions } from "./pagination.ts";
import test from "node:test";
import assert from "node:assert/strict";
import { formatAdminDate } from "./date-format.ts";
import { cleanIcon } from "./icon.ts";
import { normalizeSearch } from "./utils.ts";

test("admin dates follow the selected locale, preserve invalid values and tolerate invalid locales", () => {
    assert.equal(formatAdminDate(null, "vi"), "—");
    assert.equal(formatAdminDate("invalid", "vi"), "invalid");
    const date = "2026-10-16T10:30:00Z";
    assert.notEqual(formatAdminDate(date, "vi"), formatAdminDate(date, "en"));
    assert.equal(
        formatAdminDate(date, "invalid_locale_!"),
        formatAdminDate(date, "en"),
    );
    assert.ok(
        formatAdminDate(date, "vi", true).length >
            formatAdminDate(date, "vi").length,
    );
});
test("admin navigation search matches Vietnamese accents and trims whitespace", () => {
    assert.equal(normalizeSearch("  Đổi ngôn ngữ  "), "doi ngon ngu");
    assert.equal(normalizeSearch("Appearance"), "appearance");
});
test("icon preview keeps SVG geometry while blocking scripts, external resources and event attributes", () => {
    const cleaned = cleanIcon(
        '<svg viewBox="0 0 24 24" onload="evil()"><script>evil()</script><foreignObject><img src="https://evil.test/a" /></foreignObject><path d="M1 2L3 4" fill="url(https://evil.test/a)" onclick="evil()" /><use href="https://evil.test/a" /><circle cx="2" cy="3" r="1" fill="currentColor" /></svg>',
    );
    assert.match(cleaned, /viewBox="0 0 24 24"/);
    assert.match(cleaned, /d="M1 2L3 4"/);
    assert.match(cleaned, /fill="currentColor"/);
    assert.doesNotMatch(
        cleaned,
        /onload|onclick|<script|foreignObject|<img|<use|https:|url\(/,
    );
});

test("pagination keeps every valid current page size selectable without duplicates", () => {
    assert.deepEqual(pageSizeOptions(25), [10, 20, 25, 50, 100]);
    assert.deepEqual(pageSizeOptions(1), [1, 10, 20, 50, 100]);
    assert.deepEqual(pageSizeOptions(20), [10, 20, 50, 100]);
    for (const invalid of [0, 101, 2.5, NaN]) {
        assert.deepEqual(pageSizeOptions(invalid), [10, 20, 50, 100]);
    }
});

test("console links allow web/relative destinations while blocking executable and opaque schemes", () => {
    for (const link of [
        "https://example.test/a",
        "http://example.test/a",
        "/admin/logs",
        "missing-page",
    ])
        assert.equal(safeConsoleLink(link), link);
    for (const link of [
        "javascript:alert(1)",
        "data:text/html,evil",
        "//evil.test/a",
        "\\evil.test",
        " javaScript:alert(1)",
        "java\nscript:alert(1)",
        undefined,
    ])
        assert.equal(safeConsoleLink(link), null);
});
test("dashboard treats HTTP 200 business errors as failures instead of closing the widget dialog", () => {
    assert.throws(
        () =>
            assertAdminResult(
                { error: true, message: "Widget not found" },
                "Failed",
            ),
        /Widget not found/,
    );
    assert.throws(() => assertAdminResult({ error: true }, "Failed"), /Failed/);
    assert.doesNotThrow(() => assertAdminResult({ error: false }, "Failed"));
});

test("admin date display follows server timezone across browser zones without changing calendar dates", async () => {
    const { execFileSync } = await import("node:child_process");
    const formatter = new URL("./date-format.ts", import.meta.url).href;
    const code = `import { formatAdminDate as f } from ${JSON.stringify(formatter)};
        console.log(JSON.stringify([
            f("2026-10-17T08:08:00Z", "en-GB", true, "UTC"),
            f("2026-10-17 08:08:00", "en-GB", true, "UTC"),
            f("2026-10-17T15:08:00+07:00", "en-GB", true, "UTC"),
            f("2026-10-17T23:30:00Z", "en-GB", true, "Asia/Ho_Chi_Minh"),
            f("2026-03-08T10:30:00Z", "en-GB", true, "America/Los_Angeles"),
            f("2026-10-17", "en-GB", false, "America/Los_Angeles"),
            f("2026-10-17 08:08:00", "en-GB", true, "America/Los_Angeles"),
            f("2026-10-17T08:08:00Z", "en-GB", true, "Invalid/Zone"),
            f("2026-02-30", "en-GB", false, "UTC"),
            f("09:15", "en-GB", true, "UTC")
        ]));`;
    const expected = [
        "17 Oct 2026, 08:08",
        "17 Oct 2026, 08:08",
        "17 Oct 2026, 08:08",
        "18 Oct 2026, 06:30",
        "8 Mar 2026, 03:30",
        "17 Oct 2026",
        "17 Oct 2026, 08:08",
        "17 Oct 2026, 08:08",
        "2026-02-30",
        "09:15",
    ];
    for (const TZ of ["Asia/Ho_Chi_Minh", "America/Los_Angeles"]) {
        const result = execFileSync(
            process.execPath,
            ["--input-type=module", "-e", code],
            { env: { ...process.env, TZ }, encoding: "utf8" },
        );
        assert.deepEqual(JSON.parse(result), expected, TZ);
    }
});
