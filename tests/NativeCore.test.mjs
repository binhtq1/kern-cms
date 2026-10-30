import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";

const read = (path) => readFileSync(path, "utf8");
const classList = () => {
    const values = new Set();
    return {
        contains: (value) => values.has(value),
        add: (value) => values.add(value),
        remove: (value) => values.delete(value),
        toggle(value, active) {
            active ? values.add(value) : values.delete(value);
        },
    };
};
function element() {
    const attributes = new Map();
    return {
        nodeType: 1,
        classList: classList(),
        dataset: {},
        disabled: false,
        getAttribute: (name) => attributes.get(name) ?? null,
        setAttribute: (name, value) => attributes.set(name, String(value)),
        removeAttribute: (name) => attributes.delete(name),
        querySelectorAll: () => [],
    };
}
function core(overrides = {}) {
    const notices = [];
    const context = {
        window: {},
        document: {
            documentElement: { dir: "ltr" },
            readyState: "loading",
            addEventListener() {},
            querySelector: () => ({ content: "csrf-one" }),
        },
        Toastify(options) {
            notices.push(options);
            return { showToast() {} };
        },
        URL,
        FormData,
        AbortController,
        Event,
        CustomEvent,
        setTimeout: (callback) => {
            callback();
            return 1;
        },
        clearTimeout() {},
        ...overrides,
    };
    context.window.location ??= {
        origin: "https://cms.test",
        href: "https://cms.test/account/profile",
    };
    runInNewContext(
        read("platform/core/base/resources/js/core.js").replace(
            /^import .*\n/,
            "",
        ) + "\nglobalThis.nativeRequest = request;",
        context,
    );
    return { ...context, Kern: context.window.Kern, notices };
}

test("native core formats numeric values and reports validation without any DOM library", () => {
    const { Kern, notices } = core();
    assert.equal(Kern.numberFormat(1234.567, 2, ",", " "), "1 234,57");
    assert.equal(Kern.numberFormat("invalid"), "0");
    assert.equal(Kern.numberFormat(0.9), "1");
    assert.equal(Kern.numberFormat(-1234.5, 2), "-1,234.50");
    Kern.handleError({
        response: {
            status: 422,
            data: {
                errors: { email: ["Invalid email"], name: ["Name required"] },
            },
        },
    });
    assert.equal(notices[0].text, "Invalid email\nName required");
    assert.equal(notices[0].escapeMarkup, true);
    assert.equal(notices[0].ariaLive, "assertive");
    Kern.showError("<img src=x onerror=alert(1)>");
    assert.equal(notices[1].escapeMarkup, true);
});

test("overlapping button requests restore the original disabled and busy states only after the last request", () => {
    const { Kern } = core();
    const button = element();
    button.disabled = true;
    button.setAttribute("aria-busy", "false");
    Kern.showButtonLoading(button);
    Kern.showButtonLoading(button);
    Kern.hideButtonLoading(button);
    assert.equal(button.getAttribute("aria-busy"), "true");
    assert.equal(button.classList.contains("btn-loading"), true);
    Kern.hideButtonLoading(button);
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute("aria-busy"), "false");
    assert.equal(button.classList.contains("btn-loading"), false);
    const enabled = element();
    Kern.showButtonLoading(enabled);
    assert.equal(enabled.disabled, true);
    Kern.hideButtonLoading(enabled);
    assert.equal(enabled.disabled, false);
    assert.equal(enabled.getAttribute("aria-busy"), null);
});

test("native requests preserve the CSRF token and FormData and reject cross-origin destinations", async () => {
    const calls = [];
    const { nativeRequest } = core({
        fetch: async (url, options) => {
            calls.push({ url, options });
            return { ok: true, json: async () => ({ error: false }) };
        },
    });
    const data = new FormData();
    data.append("image", "image-data");
    await nativeRequest("/upload", { method: "POST", data });
    assert.equal(calls[0].options.headers["X-CSRF-TOKEN"], "csrf-one");
    assert.equal(calls[0].options.headers["Content-Type"], undefined);
    assert.equal(calls[0].options.credentials, "same-origin");
    assert.equal(calls[0].options.body, data);
    await nativeRequest("/search", { data: { q: "a & b", page: 2 } });
    assert.equal(calls[1].url.searchParams.get("q"), "a & b");
    assert.equal(calls[1].url.searchParams.get("page"), "2");
    await assert.rejects(
        nativeRequest("https://other.test/upload", { method: "POST", data }),
        /this website/,
    );
    assert.equal(calls.length, 2);
});

test("API validation responses and application-level errors remain failures", async () => {
    const response = {
        errors: { name: ["Required"] },
        message: "Invalid input",
    };
    const { nativeRequest } = core({
        fetch: async () => ({
            ok: false,
            status: 422,
            json: async () => response,
        }),
    });
    await assert.rejects(
        nativeRequest("/save", { method: "POST" }),
        (error) =>
            error.response.status === 422 &&
            error.response.data.errors.name[0] === "Required",
    );
    const failed = core({
        fetch: async () => ({
            ok: true,
            status: 200,
            json: async () => ({ error: true, message: "Upload failed" }),
        }),
    });
    await assert.rejects(
        failed.nativeRequest("/save", { method: "POST" }),
        /Upload failed/,
    );
});

test("localized numeric fields submit unformatted values without duplicate form keys", () => {
    const field = {
        name: "amount",
        value: "1.234,50",
        dataset: { thousandsSeparator: ".", decimalSeparator: "," },
    };
    const form = { nodeType: 1, querySelectorAll: () => [field] };
    const { Kern } = core();
    const data = new FormData();
    data.append("amount", field.value);
    Kern.unmaskInputNumber(form, data);
    assert.deepEqual(data.getAll("amount"), ["1234.50"]);
});

test("shared Axios requests read current CSRF tokens and never send them to another origin", () => {
    let interceptor, setup;
    let token = "initial-token";
    const axios = {
        interceptors: {
            request: {
                use(callback) {
                    interceptor = callback;
                },
            },
        },
    };
    class HttpClient {
        setup(callback) {
            setup = callback;
        }
    }
    const context = {
        axios,
        HttpClient,
        lodash: {},
        URL,
        window: {
            location: {
                href: "https://cms.test/account",
                origin: "https://cms.test",
            },
        },
        document: { querySelector: () => ({ content: token }) },
    };
    runInNewContext(
        read("platform/core/base/resources/js/app.js").replace(
            /^import .*\n/gm,
            "",
        ),
        context,
    );
    const config = interceptor({ url: "/save", headers: {} });
    assert.equal(config.headers["X-CSRF-TOKEN"], "initial-token");
    token = "refreshed-token";
    let before, headers;
    setup({
        config: { url: "/save" },
        beforeSend(callback) {
            before = callback;
        },
        withHeaders(value) {
            headers = value;
        },
    });
    before();
    assert.equal(headers["X-CSRF-TOKEN"], "refreshed-token");
    const external = interceptor({
        url: "https://other.test/save",
        headers: {},
    });
    assert.equal(external.headers["X-CSRF-TOKEN"], undefined);
});

test("progress stays active until all overlapping shared requests finish", () => {
    let before, complete, timer;
    let started = 0,
        completed = 0;
    const context = {
        window: {
            $httpClient: {
                beforeSend(callback) {
                    before = callback;
                },
                completed(callback) {
                    complete = callback;
                },
            },
        },
        NProgress: {
            start() {
                started++;
            },
            done() {
                completed++;
            },
            configure() {},
        },
        setTimeout(callback) {
            timer = callback;
            return 1;
        },
        clearTimeout() {
            timer = null;
        },
    };
    runInNewContext(
        read("platform/core/base/resources/js/base/progress.js")
            .replace(/^import .*\n/gm, "")
            .replace("export default ", "") +
            "\nglobalThis.setupProgress = setupProgress;",
        context,
    );
    context.setupProgress({ includeCSS: false });
    before();
    before();
    timer();
    assert.equal(started, 1);
    complete();
    assert.equal(completed, 0);
    complete();
    assert.equal(completed, 1);
});

test("native core initializes repeatedly without a global DOM library or duplicate delegated handlers", () => {
    const callbacks = new Map();
    const document = {
        documentElement: { dir: "ltr" },
        readyState: "loading",
        addEventListener(type, callback) {
            const values = callbacks.get(type) ?? [];
            values.push(callback);
            callbacks.set(type, values);
        },
        querySelectorAll: () => [],
        dispatchEvent() {},
    };
    const { Kern } = core({ document, window: { addEventListener() {} } });
    assert.doesNotThrow(() => new Kern());
    const first = [...callbacks].map(([type, values]) => [type, values.length]);
    assert.doesNotThrow(() => {
        Kern.initResources();
        Kern.initGlobalResources();
        Kern.initMediaIntegrate();
    });
    assert.deepEqual(
        [...callbacks].map(([type, values]) => [type, values.length]),
        first,
    );
});

test("native requests accept successful no-content mutations but reject unexpected HTML", async () => {
    const empty = core({
        fetch: async () => ({
            ok: true,
            status: 204,
            json: async () => {
                throw new Error("No JSON");
            },
        }),
    });
    assert.equal(
        JSON.stringify(
            await empty.nativeRequest("/remove", { method: "DELETE" }),
        ),
        "{}",
    );
    const html = core({
        fetch: async () => ({
            ok: true,
            status: 200,
            json: async () => {
                throw new SyntaxError("HTML login page");
            },
        }),
    });
    await assert.rejects(
        html.nativeRequest("/save", { method: "POST" }),
        /unexpected response/,
    );
});

test("numeric normalization keeps repeated values and omits disabled controls", () => {
    const fields = [
        { name: "prices[]", value: "1,200", dataset: {} },
        { name: "prices[]", value: "1,200", dataset: {} },
        { name: "prices[]", value: "3,400", dataset: {} },
        { name: "private", value: "9,999", dataset: {}, disabled: true },
    ];
    const form = { nodeType: 1, querySelectorAll: () => fields };
    const data = new FormData();
    for (const value of ["1,200", "1,200", "3,400"])
        data.append("prices[]", value);
    const { Kern } = core();
    Kern.unmaskInputNumber(form, data);
    assert.deepEqual(data.getAll("prices[]"), ["1200", "1200", "3400"]);
    assert.equal(data.has("private"), false);
});
