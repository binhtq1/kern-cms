import test from "node:test";
import assert from "node:assert/strict";
import { parseColor, formatColor, normalizeColorValue } from "./color.ts";

test("short and full hexadecimal preserve RGB and opacity", () => {
    assert.deepEqual(parseColor("#123"), { r: 17, g: 34, b: 51, a: 1 });
    assert.deepEqual(parseColor("#1234"), { r: 17, g: 34, b: 51, a: 68 / 255 });
    assert.equal(formatColor(parseColor("#12345680")), "#12345680");
    assert.equal(formatColor(parseColor("#AbCdEf")), "#abcdef");
});
test("RGBA and transparent normalize to backend compatible HEX8", () => {
    assert.equal(
        formatColor(parseColor("rgba(33, 79, 125, 0.5)")),
        "#214f7d80",
    );
    assert.equal(formatColor(parseColor("transparent")), "#00000000");
    assert.equal(formatColor(parseColor("rgb(33, 79, 125)")), "#214f7d");
});
test("invalid manual colors are rejected without substituting black", () => {
    for (const value of [
        "",
        "#12",
        "#12345",
        "#1234567",
        "#zzzzzz",
        "rgb(256, 0, 0)",
        "rgba(0, 0, 0, 2)",
        "rgba(-1, 0, 0, 1)",
        "url(x)",
    ])
        assert.equal(parseColor(value), null, value);
});

test("inherited heading colors round trip only for controls that allow the legacy default", () => {
    assert.equal(normalizeColorValue("inherit", true), "inherit");
    assert.equal(normalizeColorValue("  INHERIT  ", true), "inherit");
    assert.equal(normalizeColorValue("inherit"), null);
    assert.equal(parseColor("inherit"), null);
    assert.equal(normalizeColorValue("", true), "");
    assert.equal(normalizeColorValue("rgba(33,79,125,0.5)", true), "#214f7d80");
    for (const value of ["invalid", "url(x)", "var(--unknown)", "initial"])
        assert.equal(normalizeColorValue(value, true), null);
});
