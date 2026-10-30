import test from "node:test";
import assert from "node:assert/strict";
import {
    findShortcodes,
    patchShortcode,
    replaceShortcodeOccurrence,
} from "./shortcode-parser.ts";
import { escapeAttribute } from "./rich-editor-shortcodes.ts";

test("paired parser handles quoted delimiters, nested equal names and exact duplicate offsets", () => {
    const code = `[hero title='a ] b' custom="keep"][hero]nested[/hero][/hero]`;
    const source = `<p>${code}</p>\n${code}`;
    const found = findShortcodes(source);
    assert.equal(found.length, 2);
    assert.equal(found[0].content, "[hero]nested[/hero]");
    assert.equal(found[1].from, source.lastIndexOf(code));
    assert.equal(
        replaceShortcodeOccurrence(
            source,
            source,
            found[1],
            "[hero]changed[/hero]",
        ),
        `<p>${code}</p>\n[hero]changed[/hero]`,
    );
    assert.equal(
        replaceShortcodeOccurrence(
            "new " + source,
            source,
            found[1],
            "changed",
        ),
        null,
    );
});

test("parser ignores HTML attributes, comments, code samples, escaped syntax and incomplete pairs", () => {
    const source = `<a title="[hero][/hero]">Link</a><!-- [hero][/hero] --><pre>[hero][/hero]</pre><script>"[hero][/hero]"</script>[[hero]][[/hero]][outer][hero][/hero]`;
    assert.deepEqual(findShortcodes(source), []);
    assert.deepEqual(findShortcodes("[hero title='unfinished][/hero]"), []);
});

test("editing a field preserves unknown attributes, original quoting, omitted defaults and empty body", () => {
    const code =
        "[hero   title = 'Old' custom=untouched enabled=\"yes\"][/hero]";
    const initial = {
        title: "Old",
        limit: "6",
        enabled: "yes",
        custom: "untouched",
    };
    assert.equal(
        patchShortcode(code, initial, { ...initial }, "", "", escapeAttribute),
        code,
    );
    assert.equal(
        patchShortcode(
            code,
            initial,
            { ...initial, title: "It's new" },
            "",
            "",
            escapeAttribute,
        ),
        "[hero   title='It&#39;s new' custom=untouched enabled=\"yes\"][/hero]",
    );
    assert.equal(
        patchShortcode(
            code,
            initial,
            { ...initial, limit: "8" },
            "",
            "",
            escapeAttribute,
        ),
        '[hero   title = \'Old\' custom=untouched enabled="yes" limit="8"][/hero]',
    );
});

test("editing the outer attributes preserves nested content verbatim", () => {
    const code =
        '[hero title="Old"]<div>[hero title="Child"]body[/hero]</div>[/hero]';
    assert.equal(
        patchShortcode(
            code,
            { title: "Old" },
            { title: "New" },
            '<div>[hero title="Child"]body[/hero]</div>',
            '<div>[hero title="Child"]body[/hero]</div>',
            escapeAttribute,
        ),
        code.replace('title="Old"', 'title="New"'),
    );
});
