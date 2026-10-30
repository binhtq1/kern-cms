import test from "node:test";
import assert from "node:assert/strict";
import { escapeAttribute, readAttributes } from "./rich-editor-shortcodes.ts";

test("shortcode attribute escaping covers HTML, quote, bracket, and delimiter characters", () => {
    assert.equal(escapeAttribute('&"[]<>'), "&amp;&quot;&#91;&#93;&lt;&gt;");
});

test("shortcode attributes decode HTML entities through the owning document", () => {
    const document = {
        createElement(tag) {
            assert.equal(tag, "textarea");
            return {
                set innerHTML(value) {
                    this.value = value
                        .replace(/&amp;/g, "&")
                        .replace(/&quot;/g, '"')
                        .replace(/&lt;/g, "<")
                        .replace(/&gt;/g, ">")
                        .replace(/&#91;/g, "[")
                        .replace(/&#93;/g, "]");
                },
                value: "",
            };
        },
    };
    assert.deepEqual(
        readAttributes(
            '[hero title="A &amp; B &quot;yes&quot;" token="&#91;x&#93;" plain=ignored]body',
            document,
        ),
        { title: 'A & B "yes"', token: "[x]", plain: "ignored" },
    );
});
