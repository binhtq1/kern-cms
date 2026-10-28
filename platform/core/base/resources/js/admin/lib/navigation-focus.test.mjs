import test from "node:test";
import assert from "node:assert/strict";
import {
    focusPageHeading,
    installNavigationFocus,
    shouldFocusNavigation,
} from "./navigation-focus.ts";

const visit = (overrides = {}) => ({
    id: 7,
    url: "https://cms.example/admin/pages",
    method: "get",
    preserveState: false,
    preserveScroll: false,
    only: [],
    except: [],
    data: {},
    ...overrides,
});

test("navigation focus is limited to full cross-path GET visits", () => {
    assert.equal(
        shouldFocusNavigation(visit(), "https://cms.example/admin"),
        true,
    );
    for (const candidate of [
        visit({ method: "post" }),
        visit({ preserveState: true }),
        visit({ preserveScroll: true }),
        visit({ only: ["items"] }),
        visit({ except: ["flash"] }),
        visit({ data: { search: "term" } }),
        visit({ url: "https://cms.example/admin?query=1" }),
        visit({ url: "https://cms.example/admin/pages#row" }),
        visit({ url: "https://other.example/admin/pages" }),
        visit({ url: undefined }),
        visit({ url: "" }),
        visit({ id: undefined }),
    ])
        assert.equal(
            shouldFocusNavigation(candidate, "https://cms.example/admin"),
            false,
        );
});

function fakeDocument({
    activeSelector = "body",
    dialog = false,
    target = true,
    isContentEditable = false,
} = {}) {
    const focusCalls = [];
    const classes = [];
    const attributes = new Map();
    const activeElement = {
        isContentEditable,
        closest: () => null,
        matches: (selector) =>
            activeSelector !== "body" &&
            selector.split(", ").includes(activeSelector),
    };
    const heading = {
        isConnected: target,
        hasAttribute: (name) => attributes.has(name),
        setAttribute: (name, value) => attributes.set(name, value),
        classList: { add: (...names) => classes.push(...names) },
        focus: (options) => focusCalls.push(options),
    };
    return {
        doc: {
            activeElement,
            body: activeSelector === "body" ? activeElement : {},
            querySelector: (selector) => {
                if (
                    selector.includes('role="dialog"') ||
                    selector.includes("aria-modal") ||
                    selector.includes("dialog[open]")
                )
                    return dialog ? {} : null;
                return target &&
                    (selector === 'main h1, [role="main"] h1' ||
                        selector === 'main, [role="main"]')
                    ? heading
                    : null;
            },
        },
        heading,
        focusCalls,
        classes,
        attributes,
    };
}

test("page heading focus is accessible and preserves intentional user focus", () => {
    const normal = fakeDocument();
    assert.equal(focusPageHeading(normal.doc, null), true);
    assert.equal(normal.attributes.get("tabindex"), "-1");
    assert.deepEqual(normal.focusCalls, [{ preventScroll: true }]);
    assert.ok(normal.classes.includes("focus-visible:outline-2"));

    for (const state of [
        fakeDocument({ dialog: true }),
        fakeDocument({ activeSelector: "input" }),
        fakeDocument({ activeSelector: "textarea" }),
        fakeDocument({ activeSelector: "select" }),
        fakeDocument({ activeSelector: '[contenteditable="true"]' }),
        fakeDocument({
            activeSelector: '[contenteditable=""]',
            isContentEditable: true,
        }),
        fakeDocument({
            activeSelector: '[contenteditable="plaintext-only"]',
            isContentEditable: true,
        }),
        fakeDocument({ target: false }),
    ])
        assert.equal(focusPageHeading(state.doc, null), false);

    const userFocused = fakeDocument({ activeSelector: "button" });
    assert.equal(focusPageHeading(userFocused.doc, null), false);
    const navigationSource = fakeDocument({ activeSelector: "button" });
    assert.equal(
        focusPageHeading(
            navigationSource.doc,
            navigationSource.doc.activeElement,
        ),
        true,
    );
});

test("Inertia event timing focuses only matching successful visits and disposes scheduled work", () => {
    const callbacks = new Map();
    const unsubscribed = [];
    const subscribe = (name, callback) => {
        callbacks.set(name, callback);
        return () => unsubscribed.push(name);
    };
    const state = fakeDocument();
    const frames = new Map();
    let nextFrame = 1;
    const canceled = [];
    const dispose = installNavigationFocus(
        subscribe,
        state.doc,
        () => "https://cms.example/admin",
        (callback) => {
            const id = nextFrame++;
            frames.set(id, () => {
                frames.delete(id);
                callback();
            });
            return id;
        },
        (id) => {
            canceled.push(id);
            frames.delete(id);
        },
    );

    callbacks.get("start")({ detail: { visit: visit() } });
    callbacks.get("navigate")({ detail: { type: "visit", visitId: 7 } });
    callbacks.get("success")({ detail: { visitId: 8 } });
    assert.equal(frames.size, 0, "unrelated success must not move focus");
    callbacks.get("success")({ detail: { visitId: 7 } });
    assert.equal(
        frames.size,
        1,
        "focus waits until Inertia has rendered the destination",
    );
    [...frames.values()][0]();
    assert.deepEqual(state.focusCalls, [{ preventScroll: true }]);

    callbacks.get("start")({ detail: { visit: visit({ id: 9 }) } });
    callbacks.get("navigate")({ detail: { type: "visit", visitId: 9 } });
    callbacks.get("success")({ detail: { visitId: 9 } });
    const scheduled = [...frames.keys()][0];
    dispose();
    assert.ok(canceled.includes(scheduled));
    assert.deepEqual(unsubscribed.sort(), [
        "error",
        "finish",
        "navigate",
        "start",
        "success",
    ]);
    assert.equal(frames.size, 0);
});

test("history visits, failed visits, and form submissions never trigger page focus", () => {
    const callbacks = new Map();
    const state = fakeDocument();
    const frames = [];
    installNavigationFocus(
        (name, callback) => {
            callbacks.set(name, callback);
            return () => {};
        },
        state.doc,
        () => "https://cms.example/admin",
        (callback) => {
            frames.push(callback);
            return frames.length;
        },
        () => {},
    );

    callbacks.get("start")({ detail: { visit: visit({ id: "history" }) } });
    callbacks.get("navigate")({
        detail: { type: "history", visitId: "history" },
    });
    callbacks.get("success")({ detail: { visitId: "history" } });
    callbacks.get("start")({
        detail: { visit: visit({ id: "form", method: "post" }) },
    });
    callbacks.get("navigate")({ detail: { type: "visit", visitId: "form" } });
    callbacks.get("success")({ detail: { visitId: "form" } });
    callbacks.get("start")({ detail: { visit: visit({ id: "failed" }) } });
    callbacks.get("navigate")({ detail: { type: "visit", visitId: "failed" } });
    callbacks.get("error")({ detail: { visitId: "failed" } });
    callbacks.get("success")({ detail: { visitId: "failed" } });

    assert.equal(frames.length, 0);
    assert.deepEqual(state.focusCalls, []);
});

test("a focused form submitter is preserved for empty GET form navigations", () => {
    const callbacks = new Map();
    const frames = [];
    const source = {
        type: "submit",
        form: {},
        closest: () => ({}),
        matches: () => false,
        isContentEditable: false,
    };
    const body = { matches: () => false, closest: () => null };
    const heading = {
        isConnected: true,
        hasAttribute: () => true,
        classList: { add: () => {} },
        focus: () =>
            assert.fail("form submission should preserve source focus"),
    };
    const doc = {
        activeElement: source,
        body,
        querySelector: (selector) =>
            selector.includes("dialog") || selector.includes("aria-modal")
                ? null
                : heading,
    };
    installNavigationFocus(
        (name, callback) => {
            callbacks.set(name, callback);
            return () => {};
        },
        doc,
        () => "https://cms.example/admin",
        (callback) => {
            frames.push(callback);
            return frames.length;
        },
        () => {},
    );
    callbacks.get("start")({ detail: { visit: visit() } });
    callbacks.get("navigate")({ detail: { type: "visit", visitId: 7 } });
    doc.activeElement = body;
    callbacks.get("success")({ detail: { visitId: 7 } });
    frames.forEach((callback) => callback());
});
