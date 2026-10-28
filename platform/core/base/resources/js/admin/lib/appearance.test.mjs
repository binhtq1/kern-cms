import test from "node:test";
import assert from "node:assert/strict";
import {
    appearanceVariables,
    colorForeground,
    readablePrimaryColor,
} from "./appearance.ts";

test("missing appearance settings preserve the approved light and dark tokens", () => {
    assert.deepEqual(appearanceVariables(), {});
    assert.deepEqual(appearanceVariables(undefined, true), {});
});

test("brand primary colors choose the higher contrast foreground in both modes", () => {
    assert.equal(colorForeground("#fff"), "#000000");
    assert.equal(colorForeground("#000000"), "#ffffff");
    assert.equal(colorForeground("#214f7d"), "#ffffff");
    const appearance = { colors: { primary: "#ffcc00" } };

    assert.deepEqual(appearanceVariables(appearance), {
        "--primary": "#ffcc00",
        "--primary-ink": "#214f7d",
        "--ring": "#214f7d",
        "--primary-foreground": "#000000",
    });
    assert.equal(appearanceVariables(appearance, true)["--primary"], "#ffcc00");
    assert.equal(
        appearanceVariables(appearance, true)["--primary-ink"],
        "#ffcc00",
    );
    assert.equal(appearanceVariables(appearance, true)["--ring"], "#ffcc00");
    assert.equal(
        appearanceVariables(appearance, true)["--primary-foreground"],
        "#000000",
    );
});

test("an untouched appearance save cannot replace dark text and surfaces with light colors", () => {
    const appearance = {
        colors: {
            primary: "#214f7d",
            secondary: "#e9edf2",
            heading: "inherit",
            text: "#20252b",
            link: "#214f7d",
            linkHover: "#193d61",
        },
    };

    assert.equal(appearanceVariables(appearance)["--foreground"], "#20252b");
    assert.deepEqual(appearanceVariables(appearance, true), {
        "--primary": "#214f7d",
        "--primary-ink": "#95bce5",
        "--ring": "#95bce5",
        "--primary-foreground": "#ffffff",
    });
});

test("explicit typography and all six colors are reflected in light mode", () => {
    const appearance = {
        colors: {
            primary: "#123456",
            secondary: "#f0f0f0",
            heading: "#333333",
            text: "#222222",
            link: "#0066cc",
            linkHover: "#004488",
        },
        font: { family: "Inter", url: "https://example.test/font.css" },
    };

    assert.deepEqual(appearanceVariables(appearance), {
        "--primary": "#123456",
        "--primary-ink": "#123456",
        "--ring": "#123456",
        "--primary-foreground": "#ffffff",
        "--secondary": "#f0f0f0",
        "--secondary-foreground": "#000000",
        "--foreground": "#222222",
        "--admin-heading-color": "#333333",
        "--admin-link-color": "#0066cc",
        "--admin-link-hover-color": "#004488",
        "--admin-font-family": '"Inter", ui-sans-serif, system-ui, sans-serif',
    });
    assert.deepEqual(appearanceVariables({ colors: {}, font: null }), {});
});

test("primary text and controls stay readable when brand colors match the surface", () => {
    assert.equal(readablePrimaryColor("#214f7d"), "#214f7d");
    assert.equal(readablePrimaryColor("#214f7d", true), "#95bce5");
    assert.equal(readablePrimaryColor("#fff"), "#214f7d");
    assert.equal(readablePrimaryColor("#fff", true), "#fff");
    assert.equal(readablePrimaryColor("#000"), "#000");
    assert.equal(readablePrimaryColor("#000", true), "#95bce5");
    assert.equal(readablePrimaryColor("invalid"), "#214f7d");
    assert.equal(readablePrimaryColor("invalid", true), "#95bce5");
});
