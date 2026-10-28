import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";

test("clearing a table filter invalidates an in-flight value request", async () => {
    const listeners = new Map();
    let resolveResponse;
    const response = new Promise((resolve) => {
        resolveResponse = resolve;
    });
    const valueWrap = {
        innerHTML: "previous value",
        replaceChildren(...children) {
            this.children = children;
        },
    };
    const valueInput = { value: "" };
    const row = {
        querySelector(selector) {
            if (selector === ".filter-column-value") return valueInput;
            if (selector === ".filter-column-value-wrap") return valueWrap;
            if (selector === 'input.filter-column-value[type="text"]')
                return null;
            return null;
        },
    };
    const metadata = {
        value: "/filter-input",
        dataset: { value: "/filter-input" },
    };
    const wrapper = {
        querySelector(selector) {
            if (selector === ".filter-data-url") return metadata;
            if (selector === ".filter-data-class")
                return { value: "ExampleTable" };
            return null;
        },
    };
    const form = {
        closest(selector) {
            return selector === ".wrapper-filter" ? wrapper : null;
        },
    };
    const select = {
        value: "title",
        closest(selector) {
            if (selector === ".wrapper-filter") return wrapper;
            if (selector === ".filter-item") return row;
            return null;
        },
        matches(selector) {
            return selector === "form.filter-form .filter-column-key";
        },
    };
    const document = {
        readyState: "complete",
        querySelectorAll: () => [],
        addEventListener: (name, callback) => listeners.set(name, callback),
        createElement: () => ({ setAttribute() {} }),
    };
    runInNewContext(
        readFileSync("platform/core/table/resources/js/filter.js", "utf8"),
        {
            document,
            window: { Kern: {} },
            location: { href: "https://example.test/list" },
            URL,
            URLSearchParams,
            AbortController,
            crypto: { randomUUID: () => "test-id" },
            fetch: () => response,
            FormData: class {},
            history: { replaceState() {} },
            setTimeout,
            clearTimeout,
        },
    );

    listeners.get("change")({ target: select });
    select.value = "";
    listeners.get("change")({ target: select });
    resolveResponse({
        ok: true,
        json: async () => ({ html: '<input value="stale">' }),
    });
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(valueWrap.innerHTML, "previous value");
});

test("the most recently selected category keeps its form when earlier requests finish late", async () => {
    const listeners = new Map();
    const pending = new Map();
    const formBody = { innerHTML: "" };
    const formContainer = {};
    const activeRow = { classList: { add() {}, remove() {} } };
    const document = {
        readyState: "complete",
        querySelectorAll: () => [],
        querySelector: (selector) =>
            selector === ".tree-form-body"
                ? formBody
                : selector === ".tree-form-container"
                  ? formContainer
                  : null,
        addEventListener(name, callback) {
            const callbacks = listeners.get(name) || [];
            callbacks.push(callback);
            listeners.set(name, callbacks);
        },
    };
    const context = {
        document,
        window: {
            Kern: {
                showLoading() {},
                hideLoading() {},
                initResources() {},
                initMediaIntegrate() {},
                showError() {},
            },
            EDITOR: { init() {} },
            location: { href: "https://example.test/categories" },
        },
        location: { href: "https://example.test/categories", search: "" },
        URL,
        URLSearchParams,
        AbortController,
        FormData,
        fetch: (url) =>
            new Promise((resolve) => pending.set(String(url), resolve)),
    };
    runInNewContext(
        readFileSync(
            "platform/core/base/resources/js/tree-category.js",
            "utf8",
        ),
        context,
    );

    const click = (href) => {
        const target = {
            dataset: { href },
            href,
            closest(selector) {
                if (
                    selector ===
                    ".file-tree-wrapper .fetch-data, .tree-categories-create, #list-others-language a"
                )
                    return this;
                if (selector === ".dd3-content") return activeRow;
                return null;
            },
        };
        for (const handler of listeners.get("click"))
            handler({ target, preventDefault() {} });
    };
    click("/categories/first");
    click("/categories/second");

    pending.get("https://example.test/categories/second")({
        ok: true,
        json: async () => ({ data: "second form" }),
    });
    await new Promise((resolve) => setImmediate(resolve));
    pending.get("https://example.test/categories/first")({
        ok: true,
        json: async () => ({ data: "stale first form" }),
    });
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(formBody.innerHTML, "second form");
});

test("native table honors an empty sort order and configured page length", () => {
    const listeners = new Map();
    let footer;
    const makeElement = () => ({
        children: [],
        options: [],
        add(option) {
            this.options.push(option);
        },
        append(...children) {
            this.children.push(...children);
        },
        addEventListener(name, callback) {
            listeners.set(`element:${name}`, callback);
        },
        setAttribute() {},
        querySelectorAll: () => [],
        closest: () => null,
    });
    const wrapper = {
        append(element) {
            footer = element;
        },
        addEventListener() {},
    };
    const table = {
        id: "users",
        parentElement: wrapper,
        closest: () => wrapper,
        querySelectorAll: () => [],
        setAttribute() {},
    };
    const config = {
        url: "/users/table",
        columns: [
            { data: "name", name: "name", orderable: true, searchable: true },
        ],
        order: [],
        pageLength: 25,
    };
    const script = {
        dataset: { kernTable: "users" },
        textContent: JSON.stringify(config),
    };
    let request;
    const document = {
        readyState: "complete",
        querySelectorAll: (selector) =>
            selector === "script[data-kern-table]" ? [script] : [],
        getElementById: () => table,
        createElement: makeElement,
        querySelector: () => ({ content: "token" }),
        addEventListener: (name, callback) =>
            listeners.set(`document:${name}`, callback),
    };
    runInNewContext(
        readFileSync("platform/core/table/resources/js/table.js", "utf8"),
        {
            document,
            window: {},
            location: { href: "https://example.test/users", search: "" },
            URL,
            URLSearchParams,
            AbortController,
            Option: function Option(text, value) {
                this.text = text;
                this.value = value;
            },
            CustomEvent: class CustomEvent {},
            fetch: (url, options) => {
                request = { url, options };
                return new Promise(() => {});
            },
            setTimeout,
            clearTimeout,
        },
    );

    const params = new URLSearchParams(request.options.body);
    assert.equal(params.get("length"), "25");
    assert.equal(params.has("order[0][column]"), false);
    assert.equal(params.has("order[0][dir]"), false);
    assert.ok(
        footer.children[0].options.some((option) => option.value === "25"),
    );
});
