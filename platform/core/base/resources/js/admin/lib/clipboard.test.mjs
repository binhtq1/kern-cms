import assert from "node:assert/strict";
import test from "node:test";
import { copyToClipboard } from "./clipboard.ts";

async function withBrowser(browser, run) {
    const descriptors = new Map();
    for (const [key, value] of Object.entries(browser)) {
        descriptors.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
        Object.defineProperty(globalThis, key, { configurable: true, value });
    }
    try {
        await run();
    } finally {
        for (const [key, descriptor] of descriptors) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    }
}

function selectionDocument(copy) {
    const events = [];
    const savedRange = {};
    const textarea = {
        style: {},
        focus: () => events.push("focus textarea"),
        select: () => events.push("select textarea"),
        remove: () => events.push("remove textarea"),
    };
    const document = {
        body: { appendChild: (node) => assert.equal(node, textarea) },
        activeElement: {
            isConnected: true,
            focus: () => events.push("restore focus"),
        },
        getSelection: () => ({
            rangeCount: 1,
            getRangeAt: () => ({ cloneRange: () => savedRange }),
            removeAllRanges: () => events.push("clear selection"),
            addRange: (range) => {
                assert.equal(range, savedRange);
                events.push("restore selection");
            },
        }),
        createElement: (tag) => {
            assert.equal(tag, "textarea");
            return textarea;
        },
        execCommand: (command) => {
            assert.equal(command, "copy");
            assert.equal(textarea.readOnly, true);
            assert.deepEqual(events, ["focus textarea", "select textarea"]);
            return copy(textarea.value);
        },
    };
    return { document, events };
}

test("native clipboard copies exact text with its receiver and needs no document", async () => {
    const text = "Tiếng Việt\n<not-html>";
    const clipboard = {
        async writeText(value) {
            assert.equal(this, clipboard);
            assert.equal(value, text);
        },
    };
    await withBrowser({ navigator: { clipboard }, document: undefined }, () =>
        copyToClipboard(text),
    );
});

test("HTTP clipboard fallback copies literal text and restores focus and selection", async () => {
    const text = '[block alias="Tiếng Việt"]\n<script>text only</script>';
    const { document, events } = selectionDocument((value) => {
        assert.equal(value, text);
        return true;
    });
    await withBrowser({ navigator: {}, document }, () => copyToClipboard(text));
    assert.deepEqual(events.slice(-4), [
        "remove textarea",
        "restore focus",
        "clear selection",
        "restore selection",
    ]);
});

test("denied native clipboard can use the user-triggered selection fallback", async () => {
    let copied = false;
    const { document } = selectionDocument(() => (copied = true));
    await withBrowser(
        {
            navigator: {
                clipboard: {
                    async writeText() {
                        throw new Error("Permission denied");
                    },
                },
            },
            document,
        },
        () => copyToClipboard("cron command"),
    );
    assert.equal(copied, true);
});

test("failed copy never reports success and still removes temporary controls", async () => {
    for (const result of [false, new Error("Browser rejected copy")]) {
        const { document, events } = selectionDocument(() => {
            if (result instanceof Error) throw result;
            return result;
        });
        await withBrowser({ navigator: {}, document }, () =>
            assert.rejects(copyToClipboard("copy me"), /denied|rejected/),
        );
        assert.ok(events.includes("remove textarea"));
        assert.ok(events.includes("restore focus"));
        assert.ok(events.includes("restore selection"));
    }
});

test("unavailable clipboard APIs reject without claiming to copy", async () => {
    await withBrowser({ navigator: {}, document: undefined }, () =>
        assert.rejects(copyToClipboard("copy me"), /unavailable/),
    );
});
