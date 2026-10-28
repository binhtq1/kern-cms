import Toastify from "./base/toast";

const listeners = new Set();
const initialized = new WeakMap();
const loadingButtons = new WeakMap();
const loadingTargets = new WeakMap();
const selects = new WeakMap();
const dirtyForms = new WeakMap();
const variables = () => window.KernVariables ?? {};
const nodes = (value, root = document) => {
    if (!value) return [];
    if (typeof value === "string") {
        try {
            return Array.from(root.querySelectorAll(value));
        } catch {
            return [];
        }
    }
    return value.nodeType ? [value] : Array.from(value);
};
const parse = (value, fallback = null) => {
    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
};
const once = (element, key, callback) => {
    let keys = initialized.get(element);
    if (!keys) initialized.set(element, (keys = new Set()));
    if (keys.has(key)) return;
    keys.add(key);
    callback(element);
};
const visible = (element, show) => {
    if (!element) return;
    element.hidden = !show;
    element.classList.toggle("hidden", !show);
    element.classList.toggle("d-none", !show);
    if (element.classList.contains("collapse"))
        element.classList.toggle("show", show);
    if (show && element.style.display === "none")
        element.style.removeProperty("display");
};
const change = (element) =>
    element?.dispatchEvent(new Event("change", { bubbles: true }));
const text = (tag, content, className = "") => {
    const element = document.createElement(tag);
    element.textContent = content;
    element.className = className;
    return element;
};
const delegate = (type, selector, callback, key = `${type}:${selector}`) => {
    if (listeners.has(key)) return;
    listeners.add(key);
    document.addEventListener(type, (event) => {
        const target = event.target?.closest?.(selector);
        if (!target) return;
        Promise.resolve(callback(event, target)).catch((error) =>
            Kern.handleError(error),
        );
    });
};
const formSnapshot = (form) =>
    JSON.stringify(
        Array.from(new FormData(form), ([name, value]) => [
            name,
            typeof value === "string"
                ? value
                : [value.name, value.size, value.lastModified],
        ]),
    );
const request = async (url, { method = "GET", data, signal } = {}) => {
    const target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin)
        throw new Error("The request must use this website.");
    const headers = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    };
    const options = {
        method: method.toUpperCase(),
        credentials: "same-origin",
        headers,
        signal,
    };
    if (options.method === "GET") {
        for (const [key, value] of Object.entries(data ?? {}))
            target.searchParams.set(key, value ?? "");
    } else {
        headers["X-CSRF-TOKEN"] =
            document.querySelector('meta[name="csrf-token"]')?.content ?? "";
        if (data instanceof FormData) options.body = data;
        else {
            headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(data ?? {});
        }
    }
    const response = await fetch(target, options);
    let body;
    try {
        body = response.status === 204 ? {} : ((await response.json()) ?? {});
    } catch {
        throw new Error(
            `The server returned an unexpected response (${response.status}).`,
        );
    }
    if (!response.ok || body.error) {
        const error = new Error(body.message || response.statusText);
        error.response = { status: response.status, data: body };
        throw error;
    }
    return body;
};

class Kern {
    static noticesTimeout = new Map();
    static noticesTimeoutCount = 500;

    constructor() {
        this.initGlobalModal();
        this.countCharacter();
        this.manageSidebar();
        this.handleWayPoint();
        this.handleTurnOffDebugMode();
        Kern.initNavbarMinimal();
        Kern.initResources();
        Kern.initGlobalResources();
        Kern.handleCounterUp();
        Kern.initMediaIntegrate();
        this.countMenuItemNotifications();
        if (variables().authorized === "0" && variables().authorize_url)
            this.processAuthorize();
    }

    static showNotice(type, message) {
        const value = String(message ?? "");
        const key = JSON.stringify([type, value]);
        clearTimeout(Kern.noticesTimeout.get(key));
        Kern.noticesTimeout.set(
            key,
            setTimeout(() => {
                Kern.noticesTimeout.delete(key);
                Toastify({
                    text: value,
                    duration: 5000,
                    close: true,
                    gravity: "bottom",
                    position:
                        document.documentElement.dir === "rtl"
                            ? "left"
                            : "right",
                    stopOnFocus: true,
                    escapeMarkup: true,
                    ariaLive: type === "error" ? "assertive" : "polite",
                    style: {
                        background: type === "error" ? "#b42318" : "#257544",
                    },
                }).showToast();
            }, Kern.noticesTimeoutCount),
        );
    }

    static showError(message) {
        Kern.showNotice("error", message);
    }
    static showSuccess(message) {
        Kern.showNotice("success", message);
    }
    static handleError(error) {
        const data =
            error?.response?.data ?? error?.responseJSON ?? error ?? {};
        if (data.errors) Kern.handleValidationError(data.errors);
        else
            Kern.showError(
                data.message ??
                    error?.statusText ??
                    "The request could not be completed.",
            );
    }
    static handleValidationError(errors) {
        Kern.showError(
            Object.values(errors ?? {})
                .flat()
                .filter(Boolean)
                .join("\n"),
        );
    }
    static blockUI(options = {}) {
        Kern.showLoading(options.target);
    }
    static unblockUI(target) {
        Kern.hideLoading(target);
    }

    static showButtonLoading(value, overlay = true, position = "start") {
        for (const element of nodes(value)) {
            const pending = loadingButtons.get(element);
            if (pending) {
                pending.count++;
                continue;
            }
            const state = {
                count: 1,
                disabled: element.disabled,
                busy: element.getAttribute("aria-busy"),
                overlay,
                hadClass: element.classList.contains("btn-loading"),
            };
            loadingButtons.set(element, state);
            element.disabled = true;
            element.setAttribute("aria-busy", "true");
            if (overlay) element.classList.add("btn-loading");
            else {
                const spinner = text(
                    "span",
                    "",
                    "spinner-border spinner-border-sm me-2",
                );
                spinner.dataset.kernSpinner = "";
                spinner.setAttribute("aria-hidden", "true");
                position === "end"
                    ? element.append(spinner)
                    : element.prepend(spinner);
            }
        }
    }
    static hideButtonLoading(value) {
        for (const element of nodes(value)) {
            const state = loadingButtons.get(element);
            if (!state || --state.count > 0) continue;
            element.disabled = state.disabled;
            element.classList.toggle("btn-loading", state.hadClass);
            state.busy === null
                ? element.removeAttribute("aria-busy")
                : element.setAttribute("aria-busy", state.busy);
            element
                .querySelectorAll("[data-kern-spinner]")
                .forEach((spinner) => spinner.remove());
            loadingButtons.delete(element);
        }
    }
    static showLoading(value = null) {
        for (const element of nodes(value ?? ".page-wrapper")) {
            const state = loadingTargets.get(element);
            if (state) {
                state.count++;
                continue;
            }
            const spinner = text("div", "", "loading-spinner");
            spinner.setAttribute("role", "status");
            spinner.setAttribute(
                "aria-label",
                variables().languages?.loading ?? "Loading",
            );
            loadingTargets.set(element, {
                count: 1,
                positioned: element.classList.contains("position-relative"),
                busy: element.getAttribute("aria-busy"),
                spinner,
            });
            element.classList.add("position-relative");
            element.setAttribute("aria-busy", "true");
            element.append(spinner);
        }
    }
    static hideLoading(value = null) {
        for (const element of nodes(value ?? ".page-wrapper")) {
            const state = loadingTargets.get(element);
            if (!state || --state.count > 0) continue;
            state.spinner.remove();
            element.classList.toggle("position-relative", state.positioned);
            state.busy === null
                ? element.removeAttribute("aria-busy")
                : element.setAttribute("aria-busy", state.busy);
            loadingTargets.delete(element);
        }
    }

    static async copyToClipboard(value, parent = document.body) {
        if (navigator.clipboard && window.isSecureContext)
            return navigator.clipboard.writeText(String(value ?? ""));
        return Kern.unsecuredCopyToClipboard(value, parent);
    }
    static unsecuredCopyToClipboard(value, parent = document.body) {
        const previous = document.activeElement;
        const input = document.createElement("textarea");
        input.value = String(value ?? "");
        input.style.cssText = "position:fixed;left:-10000px;top:0";
        parent.append(input);
        input.select();
        try {
            if (!document.execCommand("copy"))
                throw new Error("Clipboard access failed.");
        } finally {
            input.remove();
            previous?.focus();
        }
    }
    static initClipboard() {
        delegate(
            "click",
            '[data-bb-toggle="clipboard"]',
            async (event, trigger) => {
                event.preventDefault();
                const target = nodes(trigger.dataset.clipboardTarget)[0];
                const content =
                    trigger.dataset.clipboardText ??
                    target?.value ??
                    target?.textContent ??
                    "";
                await Kern.copyToClipboard(
                    content,
                    nodes(trigger.dataset.clipboardParent)[0] ?? document.body,
                );
                if (
                    trigger.dataset.clipboardAction?.toLowerCase() === "cut" &&
                    target
                ) {
                    target.value = "";
                    change(target);
                }
                if (trigger.dataset.clipboardMessage)
                    Kern.showSuccess(trigger.dataset.clipboardMessage);
                trigger
                    .querySelectorAll("[data-clipboard-icon]")
                    .forEach((icon) => visible(icon, false));
                trigger
                    .querySelectorAll("[data-clipboard-success-icon]")
                    .forEach((icon) => visible(icon, true));
                setTimeout(() => {
                    trigger
                        .querySelectorAll("[data-clipboard-icon]")
                        .forEach((icon) => visible(icon, true));
                    trigger
                        .querySelectorAll("[data-clipboard-success-icon]")
                        .forEach((icon) => visible(icon, false));
                }, 1000);
            },
        );
    }

    static numberFormat(
        number,
        decimals = 0,
        decimalPoint = ".",
        separator = ",",
    ) {
        const value = Number.isFinite(Number(number)) ? Number(number) : 0;
        const precision = Math.min(
            100,
            Math.max(
                0,
                Number.isFinite(Number(decimals))
                    ? Math.abs(Math.trunc(Number(decimals)))
                    : 0,
            ),
        );
        const rounded =
            Math.round((value + Number.EPSILON) * 10 ** precision) /
            10 ** precision;
        const [integer, fraction] = rounded.toFixed(precision).split(".");
        return (
            integer.replace(/\B(?=(\d{3})+(?!\d))/g, separator) +
            (precision ? decimalPoint + fraction : "")
        );
    }
    static getViewPort() {
        return { width: window.innerWidth, height: window.innerHeight };
    }
    static callScroll(value) {
        for (const element of nodes(value)) {
            element.style.overflow = "auto";
            element.querySelectorAll("thead").forEach((header) => {
                header.style.position = "sticky";
                header.style.top = "0";
            });
        }
    }

    countCharacter() {
        const initialize = (element) =>
            once(element, "counter", () => {
                const limit = Number(element.dataset.counter);
                if (!Number.isFinite(limit) || limit < 0) return;
                const counter = text("small", "", "charcounter");
                counter.setAttribute("aria-live", "polite");
                element.after(counter);
                const update = () => {
                    if (
                        !element.hasAttribute("data-allow-over-limit") &&
                        element.value.length > limit
                    )
                        element.value = element.value.slice(0, limit);
                    counter.textContent = `(${element.value.length}/${limit})`;
                    counter.classList.toggle(
                        "text-danger",
                        element.value.length > limit,
                    );
                };
                element.addEventListener("input", update);
                update();
            });
        nodes("input[data-counter], textarea[data-counter]").forEach(
            initialize,
        );
        delegate(
            "focusin",
            "input[data-counter], textarea[data-counter]",
            (_event, element) => initialize(element),
        );
    }

    static select(value, options = {}, force = false) {
        for (const element of nodes(value)) {
            if (element.tagName !== "SELECT") continue;
            if (selects.has(element)) {
                if (!force) continue;
                selects.get(element).destroy();
            }
            const remote =
                options.ajax ??
                (element.dataset.url
                    ? {
                          url: element.dataset.url,
                          type: element.dataset.type ?? "GET",
                          data: ({ term, page }) => ({
                              [element.classList.contains("select-search-ajax")
                                  ? "search"
                                  : "q"]: term,
                              page,
                          }),
                      }
                    : null);
            const searchable =
                remote ||
                element.matches(
                    ".select-search-full, .select-autocomplete, .select-search-ajax, [data-bb-core-icon]",
                );
            if (!searchable) {
                selects.set(element, {
                    destroy() {
                        selects.delete(element);
                    },
                });
                continue;
            }
            const search = document.createElement("input");
            search.type = "search";
            search.className = "form-control mb-2";
            const label = Array.from(element.labels ?? [])
                .map((label) => label.textContent.trim())
                .join(" ");
            search.setAttribute(
                "aria-label",
                options.placeholder ||
                    element.dataset.placeholder ||
                    label ||
                    "Search options",
            );
            search.placeholder =
                element.dataset.placeholder || label || "Search options";
            const status = text("span", "", "form-text");
            status.setAttribute("role", "status");
            const more = text(
                "button",
                variables().languages?.load_more ?? "Load more",
                "btn btn-outline-secondary btn-sm",
            );
            more.type = "button";
            more.hidden = true;
            element.before(search);
            element.after(status, more);
            const original = Array.from(element.options);
            let controller,
                timer,
                page = 1,
                currentTerm = "";
            const load = async (append = false) => {
                controller?.abort();
                controller = new AbortController();
                const active = controller;
                element.setAttribute("aria-busy", "true");
                more.disabled = true;
                try {
                    const response = await request(
                        typeof remote.url === "function"
                            ? remote.url()
                            : remote.url,
                        {
                            method: remote.type ?? remote.method ?? "GET",
                            data: remote.data?.({
                                term: currentTerm,
                                page,
                            }) ?? { q: currentTerm, page },
                            signal: active.signal,
                        },
                    );
                    if (
                        active.signal.aborted ||
                        active !== controller ||
                        !element.isConnected
                    )
                        return;
                    const normalized = remote.processResults?.(response) ?? {
                        results: Array.isArray(response.data)
                            ? response.data
                            : (response.data?.data ??
                              response.data ??
                              response),
                        pagination: {
                            more: Boolean(
                                response.links?.next ??
                                response.data?.next_page_url,
                            ),
                        },
                    };
                    const results = Array.isArray(normalized.results)
                        ? normalized.results
                        : Object.entries(normalized.results ?? {}).map(
                              ([id, name]) => ({ id, text: name }),
                          );
                    if (!append)
                        Array.from(element.options)
                            .filter((option) => !option.selected)
                            .forEach((option) => option.remove());
                    for (const item of results) {
                        const id = String(item.id ?? item.value ?? "");
                        if (
                            Array.from(element.options).some(
                                (option) => option.value === id,
                            )
                        )
                            continue;
                        element.add(
                            new Option(
                                String(
                                    item.text ?? item.name ?? item.label ?? id,
                                ),
                                id,
                            ),
                        );
                    }
                    more.hidden = !normalized.pagination?.more;
                    status.textContent =
                        String(results.length) +
                        " " +
                        (variables().languages?.results ?? "results");
                } catch (error) {
                    if (error.name !== "AbortError") {
                        status.textContent = error.message;
                        more.hidden = true;
                    }
                } finally {
                    if (active === controller) {
                        element.removeAttribute("aria-busy");
                        more.disabled = false;
                    }
                }
            };
            search.addEventListener("input", () => {
                currentTerm = search.value.trim();
                if (!remote) {
                    for (const option of original) {
                        option.hidden =
                            !option.selected &&
                            !option.textContent
                                .toLowerCase()
                                .includes(currentTerm.toLowerCase());
                    }
                    return;
                }
                controller?.abort();
                clearTimeout(timer);
                page = 1;
                if (
                    currentTerm.length <
                    Number(
                        options.minimumInputLength ??
                            element.dataset.minimumInput ??
                            1,
                    )
                ) {
                    more.hidden = true;
                    return;
                }
                timer = setTimeout(
                    () => load(),
                    Number(remote.delay ?? options.delay ?? 250),
                );
            });
            more.addEventListener("click", () => {
                page++;
                load(true);
            });
            const selected = parse(element.dataset.selected, {});
            for (const [id, name] of Object.entries(selected)) {
                if (
                    !Array.from(element.options).some(
                        (option) => option.value === id,
                    )
                )
                    element.add(new Option(String(name), id, true, true));
            }
            selects.set(element, {
                destroy() {
                    controller?.abort();
                    clearTimeout(timer);
                    search.remove();
                    status.remove();
                    more.remove();
                    original.forEach((option) => {
                        option.hidden = false;
                    });
                    selects.delete(element);
                },
            });
        }
    }
    static initCoreIcon() {
        Kern.select("[data-bb-core-icon]");
    }
    static initTreeCategoriesSelect() {
        Kern.select('[data-bb-toggle="tree-categories-select"]');
    }

    static initDatePicker(value) {
        if (typeof window.flatpickr !== "function") return;
        for (const element of nodes(value)) {
            const input = element.matches("input")
                ? element
                : element.querySelector("input");
            if (!input || input._flatpickr || element._flatpickr) continue;
            const locale =
                window.siteEditorLocale === "vi"
                    ? "vn"
                    : window.siteEditorLocale;
            const options = {
                dateFormat: input.dataset.dateFormat ?? "Y-m-d",
                ...parse(input.dataset.options, {}),
            };
            if (locale && window.flatpickr.l10ns?.[locale])
                options.locale = locale;
            if (input !== element) options.wrap = true;
            window.flatpickr(element, options);
        }
    }
    static initColorPicker() {
        for (const element of nodes("[data-bb-color-picker], .color-picker"))
            once(element, "color", () => {
                const control = document.createElement("input");
                control.type = "color";
                control.disabled = element.disabled || element.readOnly;
                control.className = "form-control form-control-color";
                control.setAttribute(
                    "aria-label",
                    element.labels?.[0]?.textContent ?? "Choose color",
                );
                const sync = () => {
                    if (/^#[\da-f]{6}$/i.test(element.value))
                        control.value = element.value;
                };
                control.addEventListener("input", () => {
                    element.value = control.value;
                    element.dispatchEvent(
                        new Event("input", { bubbles: true }),
                    );
                    change(element);
                });
                element.addEventListener("input", sync);
                element.after(control);
                sync();
            });
    }
    static initCodeEditor(value, mode = "css") {
        const element =
            typeof value === "string" ? document.getElementById(value) : value;
        if (!element || !window.CodeMirror) return;
        once(element, "code-editor", () => {
            const editor = window.CodeMirror.fromTextArea(element, {
                extraKeys: { "Ctrl-Space": "autocomplete" },
                lineNumbers: true,
                mode,
                lineWrapping: true,
            });
            editor.on("change", () => {
                editor.save();
                element.dispatchEvent(new Event("input", { bubbles: true }));
            });
            const handle = text("button", "↕", "handle-tool-drag");
            handle.type = "button";
            handle.setAttribute("aria-label", "Resize editor");
            element.parentElement.append(handle);
            handle.addEventListener("pointerdown", (event) => {
                const start = event.clientY,
                    height = editor.getWrapperElement().offsetHeight;
                handle.setPointerCapture(event.pointerId);
                const move = (current) =>
                    editor.setSize(
                        null,
                        Math.max(200, height + current.clientY - start),
                    );
                handle.addEventListener("pointermove", move);
                handle.addEventListener(
                    "pointerup",
                    () => handle.removeEventListener("pointermove", move),
                    { once: true },
                );
            });
            handle.addEventListener("keydown", (event) => {
                if (!["ArrowUp", "ArrowDown"].includes(event.key)) return;
                event.preventDefault();
                editor.setSize(
                    null,
                    Math.max(
                        200,
                        editor.getWrapperElement().offsetHeight +
                            (event.key === "ArrowDown" ? 40 : -40),
                    ),
                );
            });
        });
    }
    static initCodeEditorComponent() {
        nodes("textarea[data-bb-code-editor]").forEach((element) =>
            Kern.initCodeEditor(element, element.dataset.mode ?? "htmlmixed"),
        );
    }

    static initResources() {
        Kern.select(
            "select.select-search-full, select.select-full, select.select-autocomplete, select.select-search-ajax, select.select-multiple",
        );
        Kern.initDatePicker(".datepicker");
        Kern.initColorPicker();
        Kern.initCodeEditorComponent();
        Kern.initCoreIcon();
        Kern.initTreeCategoriesSelect();
        Kern.initLightbox();
        for (const element of nodes('[data-bs-toggle="tooltip"]'))
            window.bootstrap?.Tooltip?.getOrCreateInstance(element);
        for (const element of nodes("textarea.textarea-auto-height"))
            once(element, "autosize", () => {
                const resize = () => {
                    element.style.height = "auto";
                    element.style.height = `${element.scrollHeight}px`;
                };
                element.addEventListener("input", resize);
                resize();
            });
        for (const form of nodes("form"))
            once(form, "numeric-submit", () => {
                form.addEventListener("formdata", (event) =>
                    Kern.unmaskInputNumber(form, event.formData),
                );
                for (const input of form.querySelectorAll(".input-mask-number"))
                    input.inputMode = "decimal";
            });
        for (const form of nodes("form.dirty-check"))
            once(form, "dirty", () => {
                dirtyForms.set(form, {
                    baseline: formSnapshot(form),
                    dirty: false,
                });
                const update = () => {
                    dirtyForms.get(form).dirty =
                        formSnapshot(form) !== dirtyForms.get(form).baseline;
                };
                form.addEventListener("input", update);
                form.addEventListener("change", update);
                form.addEventListener("core:form-saved", () => {
                    dirtyForms.set(form, {
                        baseline: formSnapshot(form),
                        dirty: false,
                    });
                });
            });
        if (!listeners.has("beforeunload")) {
            listeners.add("beforeunload");
            window.addEventListener("beforeunload", (event) => {
                if (
                    !nodes("form.dirty-check").some(
                        (form) => dirtyForms.get(form)?.dirty,
                    )
                )
                    return;
                event.preventDefault();
                event.returnValue = "";
            });
        }
        document.dispatchEvent(new CustomEvent("core-init-resources"));
    }

    static initFieldCollapse() {
        const update = (element, toggle = false) => {
            for (const target of nodes(element.dataset.bbTarget)) {
                let show;
                if (element.type === "checkbox")
                    show = parse(
                        element.dataset.bbReverse,
                        Boolean(element.dataset.bbReverse),
                    )
                        ? !element.checked
                        : element.checked;
                else if (
                    element.type === "radio" ||
                    element.tagName === "SELECT"
                ) {
                    if (element.type === "radio" && !element.checked) continue;
                    const expected = parse(
                        target.dataset.bbValue,
                        target.dataset.bbValue,
                    );
                    show = (
                        Array.isArray(expected) ? expected : [expected]
                    ).some((value) => String(value) === element.value);
                } else
                    show = toggle
                        ? target.hidden ||
                          target.classList.contains("d-none") ||
                          getComputedStyle(target).display === "none"
                        : !target.hidden;
                visible(target, show);
                element.setAttribute("aria-expanded", String(show));
            }
        };
        nodes('[data-bb-toggle="collapse"]').forEach((element) =>
            update(element),
        );
        delegate("change", '[data-bb-toggle="collapse"]', (_event, element) =>
            update(element),
        );
        delegate("click", '[data-bb-toggle="collapse"]', (event, element) => {
            if (element.type === "button" || element.tagName === "A") {
                event.preventDefault();
                update(element, true);
            }
        });
        for (const target of nodes("[data-bb-collapse][data-bb-trigger]")) {
            for (const trigger of nodes(target.dataset.bbTrigger))
                once(trigger, `collapse:${target.dataset.bbTrigger}`, () => {
                    const refresh = () => {
                        for (const item of nodes(
                            "[data-bb-collapse][data-bb-trigger]",
                        )) {
                            if (
                                item.dataset.bbTrigger !==
                                target.dataset.bbTrigger
                            )
                                continue;
                            const expected = parse(
                                item.dataset.bbValue,
                                item.dataset.bbValue,
                            );
                            visible(
                                item,
                                (trigger.type !== "checkbox" ||
                                    trigger.checked) &&
                                    (Array.isArray(expected)
                                        ? expected
                                        : [expected]
                                    ).some(
                                        (value) =>
                                            String(value) === trigger.value,
                                    ),
                            );
                        }
                    };
                    trigger.addEventListener("change", refresh);
                    refresh();
                });
        }
    }
    static initTreeCheckboxes() {
        const updateParent = (item) => {
            let parent = item.closest("ul")?.closest("li");
            while (parent) {
                const checkbox = parent.querySelector(
                    ':scope > .form-check input[type="checkbox"], :scope > label input[type="checkbox"]',
                );
                const children = Array.from(
                    parent.querySelectorAll('ul input[type="checkbox"]'),
                );
                if (checkbox && children.length) {
                    const checked = children.filter(
                        (input) => input.checked,
                    ).length;
                    checkbox.checked = checked === children.length;
                    checkbox.indeterminate =
                        checked > 0 && checked < children.length;
                }
                parent = parent.parentElement?.closest("li");
            }
        };
        const selector =
            '[data-bb-toggle="tree-checkboxes"] input[type="checkbox"]';
        delegate("change", selector, (_event, input) => {
            input
                .closest("li")
                ?.querySelectorAll('ul input[type="checkbox"]')
                .forEach((child) => {
                    child.checked = input.checked;
                    child.indeterminate = false;
                });
            updateParent(input);
        });
        nodes(selector).reverse().forEach(updateParent);
    }
    static initDropdownCheckboxes() {
        const refresh = (wrapper) => {
            const selected = Array.from(
                wrapper.querySelectorAll('input[type="checkbox"]:checked'),
            );
            const title = wrapper.querySelector(":scope > span");
            if (title)
                title.textContent =
                    selected.length > 3
                        ? `${selected.length} ${wrapper.dataset.selectedText ?? "selected"}`
                        : selected
                              .map(
                                  (input) =>
                                      input
                                          .closest("label")
                                          ?.textContent.trim() ?? input.value,
                              )
                              .join(", ") ||
                          wrapper.dataset.placeholder ||
                          " ";
            const destination = wrapper.querySelector(
                ".multi-checklist-selected",
            );
            if (!destination || !wrapper.dataset.name) return;
            destination.replaceChildren(
                ...selected.map((item) => {
                    const input = document.createElement("input");
                    input.type = "hidden";
                    input.name = wrapper.dataset.name;
                    input.value = item.value;
                    return input;
                }),
            );
        };
        nodes('[data-bb-toggle="dropdown-checkboxes"]').forEach(refresh);
        delegate(
            "change",
            '[data-bb-toggle="dropdown-checkboxes"] input[type="checkbox"]',
            (_event, input) =>
                refresh(
                    input.closest('[data-bb-toggle="dropdown-checkboxes"]'),
                ),
        );
        delegate(
            "click",
            '[data-bb-toggle="dropdown-checkboxes"] > span',
            async (event, title) => {
                event.stopPropagation();
                const wrapper = title.parentElement;
                visible(title, false);
                const search = wrapper.querySelector(
                    ':scope > input[type="text"]',
                );
                visible(search, true);
                search?.focus();
                wrapper.querySelector(".dropdown-menu")?.classList.add("show");
                if (!wrapper.dataset.ajaxUrl || wrapper.dataset.kernLoaded)
                    return;
                const response = await request(wrapper.dataset.ajaxUrl);
                const values = response.data ?? response;
                const list = wrapper.querySelector("ul");
                if (!list) return;
                const selected = Array.from(
                    wrapper.querySelectorAll(".multi-checklist-selected input"),
                ).map((input) => input.value);
                list.replaceChildren(
                    ...Object.entries(values).map(([id, label]) => {
                        const item = document.createElement("li"),
                            control = document.createElement("label"),
                            input = document.createElement("input");
                        control.className = "form-check";
                        input.type = "checkbox";
                        input.className = "form-check-input";
                        input.value = id;
                        input.checked = selected.includes(id);
                        control.append(
                            input,
                            text("span", String(label), "form-check-label"),
                        );
                        item.append(control);
                        return item;
                    }),
                );
                wrapper.dataset.kernLoaded = "1";
                refresh(wrapper);
            },
        );
        delegate(
            "input",
            '[data-bb-toggle="dropdown-checkboxes"] > input[type="text"]',
            (_event, input) => {
                input.parentElement
                    .querySelectorAll("li")
                    .forEach((item) =>
                        visible(
                            item,
                            item.textContent
                                .toLowerCase()
                                .includes(input.value.trim().toLowerCase()),
                        ),
                    );
            },
        );
        if (!listeners.has("close-dropdown-checkboxes")) {
            listeners.add("close-dropdown-checkboxes");
            document.addEventListener("click", (event) => {
                if (
                    event.target.closest(
                        '[data-bb-toggle="dropdown-checkboxes"]',
                    )
                )
                    return;
                nodes('[data-bb-toggle="dropdown-checkboxes"]').forEach(
                    (wrapper) => {
                        wrapper
                            .querySelector(".dropdown-menu")
                            ?.classList.remove("show");
                        visible(wrapper.querySelector(":scope > span"), true);
                        visible(
                            wrapper.querySelector(
                                ':scope > input[type="text"]',
                            ),
                            false,
                        );
                    },
                );
            });
        }
    }
    static initGlobalResources() {
        Kern.initFieldCollapse();
        Kern.initTreeCheckboxes();
        Kern.initClipboard();
        Kern.initDropdownCheckboxes();
        delegate("change", ".media-image-input", (_event, input) => {
            const file = input.files?.[0];
            if (!file) return;
            const preview = input
                .closest(".image-box")
                ?.querySelector(".preview-image");
            if (!preview) return;
            const reader = new FileReader();
            reader.addEventListener("load", () => {
                preview.src = reader.result;
                visible(preview, true);
            });
            reader.readAsDataURL(file);
        });
        delegate("click", ".media-select-file", (event, trigger) => {
            event.preventDefault();
            trigger
                .closest(".attachment-wrapper")
                ?.querySelector(".media-file-input")
                ?.click();
        });
        delegate(
            "change",
            '.check-all, [data-bb-toggle="check-all"]',
            (_event, trigger) => {
                const control = trigger.matches("input")
                    ? trigger
                    : trigger.querySelector('input[type="checkbox"]');
                if (!control) return;
                nodes(
                    trigger.dataset.bbTarget ??
                        trigger.dataset.target ??
                        trigger.dataset.set,
                ).forEach((input) => {
                    if (input !== control) {
                        input.checked = control.checked;
                        change(input);
                    }
                });
            },
        );
    }

    initGlobalModal() {
        delegate("click", '[data-bb-toggle="modal"]', (event, trigger) => {
            event.preventDefault();
            const modal = document.getElementById(
                `global-${trigger.dataset.type}-modal`,
            );
            if (!modal || !window.bootstrap?.Modal) return;
            const body = modal.querySelector(".modal-body");
            let title = modal.querySelector(".modal-title, .modal-body h3");
            if (!title && body) {
                title = text("h3", "");
                body.append(title);
            }
            if (title)
                title.textContent =
                    trigger.querySelector(".modal-replace-title")
                        ?.textContent ?? "";
            let description = modal.querySelector(
                ".modal-body .modal-description, .modal-body .text-secondary, .modal-body .text-muted",
            );
            if (!description && body) {
                description = text("p", "", "modal-description text-muted");
                body.append(description);
            }
            if (description)
                description.textContent =
                    trigger.querySelector(".modal-replace-description")
                        ?.textContent ?? "";
            const button = modal.querySelector(
                ".confirm-trigger-single-action-button, .modal-footer button:not([data-bs-dismiss])",
            );
            const footer = modal.querySelector(".modal-footer");
            visible(footer, parse(trigger.dataset.actionModal, false));
            const cancel = footer?.querySelector('[data-bs-dismiss="modal"]');
            if (cancel && trigger.dataset.cancelText)
                cancel.textContent = trigger.dataset.cancelText;
            if (button) {
                button.textContent =
                    trigger.dataset.confirmText ?? button.textContent;
                button.onclick = async () => {
                    Kern.showButtonLoading(button);
                    try {
                        await request(trigger.dataset.url, {
                            method: trigger.dataset.method ?? "POST",
                            data: parse(trigger.dataset.payload, {}),
                        });
                        window.bootstrap.Modal.getOrCreateInstance(
                            modal,
                        ).hide();
                    } catch (error) {
                        Kern.handleError(error);
                    } finally {
                        Kern.hideButtonLoading(button);
                    }
                };
            }
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    }
    manageSidebar() {
        delegate("click", ".sidebar-toggle.d-none", (event) => {
            event.preventDefault();
            document.body.classList.toggle("sidebar-narrow");
            document.body.classList.toggle("page-sidebar-closed");
        });
    }
    static initNavbarMinimal() {
        delegate(
            "click",
            '[data-bb-toggle="navbar-minimal"]',
            async (event, trigger) => {
                event.preventDefault();
                const navbar = nodes(trigger.dataset.bbTarget)[0];
                if (!navbar) return;
                navbar.classList.toggle("navbar-minimal");
                if (trigger.dataset.url)
                    await request(trigger.dataset.url, {
                        method: trigger.dataset.method ?? "POST",
                        data: {
                            minimal_sidebar: navbar.classList.contains(
                                "navbar-minimal",
                            )
                                ? "yes"
                                : "no",
                        },
                    });
            },
        );
    }
    handleWayPoint() {
        const elements = nodes("[data-bb-waypoint]");
        if (!window.IntersectionObserver) return;
        const observer = new IntersectionObserver((entries) =>
            entries.forEach((entry) =>
                nodes(entry.target.dataset.bbTarget).forEach((target) =>
                    visible(target, entry.isIntersecting),
                ),
            ),
        );
        elements.forEach((element) => observer.observe(element));
    }
    static handleCounterUp() {
        for (const element of nodes('[data-counter="counterup"]'))
            once(element, "counterup", () => {
                const target = Number(element.textContent.replaceAll(",", ""));
                if (
                    !Number.isFinite(target) ||
                    window.matchMedia?.("(prefers-reduced-motion: reduce)")
                        .matches
                )
                    return;
                const started = performance.now();
                const animate = (now) => {
                    const progress = Math.min(1, (now - started) / 700);
                    element.textContent = Kern.numberFormat(
                        target * (1 - (1 - progress) ** 3),
                    );
                    if (progress < 1) requestAnimationFrame(animate);
                };
                requestAnimationFrame(animate);
            });
    }
    handleTurnOffDebugMode() {
        delegate(
            "click",
            '#debug-mode-turn-off-confirmation-modal button[type="submit"]',
            async (event, button) => {
                event.preventDefault();
                Kern.showButtonLoading(button);
                try {
                    const result = await request(
                        button.dataset.url ?? button.form?.action,
                        { method: "POST" },
                    );
                    Kern.showSuccess(result.message);
                    window.bootstrap?.Modal?.getInstance(
                        button.closest(".modal"),
                    )?.hide();
                } finally {
                    Kern.hideButtonLoading(button);
                }
            },
        );
    }
    async processAuthorize() {
        try {
            await request(variables().authorize_url, { method: "POST" });
        } catch {}
    }
    async countMenuItemNotifications() {
        const items = nodes(".menu-item-count");
        const url = items[0]?.dataset.url ?? variables().menu_item_count_url;
        if (!items.length || !url) return;
        try {
            const response = await request(url);
            for (const item of response.data ?? [])
                for (const element of items.filter((element) =>
                    element.classList.contains(String(item.key)),
                )) {
                    element.textContent = String(item.value);
                    visible(element, Number(item.value) > 0);
                }
        } catch (error) {
            Kern.handleError(error);
        }
    }

    static lightbox(sources) {
        if (!window.FsLightbox) return null;
        const instance = new window.FsLightbox();
        if (Array.isArray(sources)) {
            instance.props.sources = sources;
            instance.open();
        }
        return instance;
    }
    static initLightbox() {
        const groups = (window.lightboxInstance ??= {});
        for (const element of nodes("a[data-bb-lightbox]"))
            once(element, "lightbox", () => {
                const name = element.dataset.bbLightbox;
                const instance = (groups[name] ??= Kern.lightbox());
                if (!instance) return;
                const index = instance.props.sources.push(element.href) - 1;
                element.addEventListener("click", (event) => {
                    event.preventDefault();
                    instance.open(index);
                });
            });
    }

    static openMediaUsing(callback, options = {}) {
        const dialog = document.createElement("dialog");
        dialog.className = "rounded border p-4";
        dialog.setAttribute(
            "aria-label",
            variables().languages?.select_media ?? "Choose media",
        );
        const form = document.createElement("form");
        const label = text(
            "label",
            variables().languages?.image_url ?? "Image URL",
        );
        const input = document.createElement("input");
        input.type = "url";
        input.required = true;
        input.className = "form-control my-3";
        input.setAttribute("aria-label", label.textContent);
        label.append(input);
        const save = text(
            "button",
            variables().languages?.select ?? "Select",
            "btn btn-primary",
        );
        save.type = "submit";
        const cancel = text(
            "button",
            variables().languages?.cancel ?? "Cancel",
            "btn btn-secondary ms-2",
        );
        cancel.type = "button";
        const close = () => {
            dialog.close();
            dialog.remove();
        };
        cancel.addEventListener("click", close);
        dialog.addEventListener("cancel", () => dialog.remove(), {
            once: true,
        });
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            const url = new URL(input.value, window.location.href);
            if (!["https:", "http:"].includes(url.protocol)) return;
            callback([{ url: url.href, full_url: url.href, thumb: url.href }]);
            close();
        });
        const uploadUrl =
            options.uploadUrl ?? window.RV_MEDIA_URL?.media_upload_from_editor;
        if (uploadUrl) {
            const upload = document.createElement("input");
            upload.type = "file";
            upload.accept = "image/*";
            upload.multiple = Boolean(options.multiple);
            upload.setAttribute(
                "aria-label",
                variables().languages?.upload ?? "Upload images",
            );
            upload.addEventListener("change", async () => {
                Kern.showButtonLoading(save);
                const files = [];
                try {
                    for (const file of upload.files ?? []) {
                        const data = new FormData();
                        data.append("upload", file);
                        const response = await request(uploadUrl, {
                            method: "POST",
                            data,
                        });
                        const result = response.data ?? response;
                        const url = result.url ?? result.file?.url;
                        if (!url)
                            throw new Error(
                                "The upload did not return an image URL.",
                            );
                        files.push({
                            ...result,
                            url,
                            full_url: result.full_url ?? url,
                            thumb: result.thumb ?? url,
                        });
                    }
                    if (files.length) {
                        callback(files);
                        close();
                    }
                } catch (error) {
                    Kern.handleError(error);
                } finally {
                    Kern.hideButtonLoading(save);
                }
            });
            form.append(upload);
        }
        form.prepend(label);
        form.append(save, cancel);
        dialog.append(form);
        document.body.append(dialog);
        dialog.showModal();
        input.focus();
    }
    static handleOpenMedia(item) {
        item?.click();
    }
    static initMediaIntegrate() {
        const refreshGallery = (list) => {
            const wrapper = list.closest(".list-images");
            const hasImages = Boolean(
                list.querySelector(".gallery-image-item-handler"),
            );
            visible(list, hasImages);
            visible(
                wrapper?.querySelector(".default-placeholder-gallery-image"),
                !hasImages,
            );
            visible(wrapper?.querySelector(".footer-action"), hasImages);
        };
        const addImage = (list, file) => {
            const item = text(
                "div",
                "",
                "col-lg-2 col-md-3 col-4 gallery-image-item-handler mb-2",
            );
            const box = text("div", "", "custom-image-box image-box");
            const input = document.createElement("input");
            input.type = "hidden";
            input.className = "image-data";
            input.name = list.dataset.name;
            input.value = file.url;
            const preview = document.createElement("img");
            preview.className = "preview-image w-100";
            preview.src = file.full_url ?? file.url;
            preview.alt = "";
            const edit = text(
                "button",
                variables().languages?.edit ?? "Edit image",
                "btn btn-sm",
            );
            edit.type = "button";
            edit.dataset.bbToggle = "image-picker-edit";
            const remove = text(
                "button",
                variables().languages?.remove ?? "Remove image",
                "btn btn-sm",
            );
            remove.type = "button";
            remove.dataset.bbToggle = "image-picker-remove";
            box.append(input, preview, edit, remove);
            item.append(box);
            list.append(item);
            change(input);
            Kern.initGalleryOrdering(list);
            refreshGallery(list);
        };
        nodes(".list-gallery-media-images").forEach((list) =>
            Kern.initGalleryOrdering(list),
        );
        delegate(
            "click",
            '[data-bb-toggle="gallery-add"], [data-bb-toggle="image-picker-edit"]',
            (event, trigger) => {
                event.preventDefault();
                const list = trigger
                    .closest(".gallery-images-wrapper")
                    ?.querySelector(".list-gallery-media-images");
                if (!list) return;
                Kern.openMediaUsing(
                    (files) => {
                        const item = trigger.closest(
                            ".gallery-image-item-handler",
                        );
                        const [first, ...remaining] = files;
                        if (item && first) {
                            const input = item.querySelector(".image-data"),
                                preview = item.querySelector(".preview-image");
                            if (input) {
                                input.value = first.url;
                                change(input);
                            }
                            if (preview)
                                preview.src = first.full_url ?? first.url;
                            remaining.forEach((file) => addImage(list, file));
                        } else files.forEach((file) => addImage(list, file));
                        refreshGallery(list);
                    },
                    { multiple: true, uploadUrl: trigger.dataset.uploadUrl },
                );
            },
        );
        delegate(
            "click",
            '[data-bb-toggle="image-picker-choose"]',
            (event, trigger) => {
                event.preventDefault();
                const box = trigger.closest(".image-box");
                if (trigger.dataset.target === "direct") {
                    box?.querySelector(".media-image-input")?.click();
                    return;
                }
                Kern.openMediaUsing(
                    (files) => {
                        const file = files[0],
                            input = box?.querySelector(".image-data"),
                            preview = box?.querySelector(".preview-image");
                        if (input) {
                            input.value = file.url;
                            change(input);
                        }
                        if (preview) {
                            preview.src = file.full_url ?? file.url;
                            preview.classList.remove("default-image");
                            visible(preview, true);
                        }
                        visible(
                            box?.querySelector(
                                '[data-bb-toggle="image-picker-remove"]',
                            ),
                            true,
                        );
                        document.dispatchEvent(
                            new CustomEvent("core-insert-media", {
                                detail: { files, element: trigger },
                            }),
                        );
                    },
                    { uploadUrl: trigger.dataset.uploadUrl },
                );
            },
        );
        delegate(
            "click",
            '[data-bb-toggle="image-picker-remove"]',
            (event, trigger) => {
                event.preventDefault();
                const galleryItem = trigger.closest(
                    ".gallery-image-item-handler",
                );
                if (galleryItem) {
                    const list = galleryItem.closest(".list-images");
                    galleryItem.remove();
                    if (!list?.querySelector(".gallery-image-item-handler")) {
                        visible(
                            list?.querySelector(
                                ".default-placeholder-gallery-image",
                            ),
                            true,
                        );
                        visible(list?.querySelector(".footer-action"), false);
                    }
                    return;
                }
                const box = trigger.closest(".image-box"),
                    input = box?.querySelector(".image-data"),
                    preview = box?.querySelector(".preview-image");
                if (input) {
                    input.value = "";
                    change(input);
                }
                if (preview) {
                    preview.src = preview.dataset.default ?? "";
                    preview.classList.add("default-image");
                }
                visible(trigger, false);
            },
        );
        delegate(
            "click",
            '[data-bb-toggle="media-file-remove"]',
            (event, trigger) => {
                event.preventDefault();
                const wrapper = trigger.closest(".attachment-wrapper"),
                    input = wrapper?.querySelector(".attachment-url");
                if (input) {
                    input.value = "";
                    change(input);
                }
                visible(wrapper?.querySelector(".attachment-details"), false);
                visible(trigger, false);
            },
        );
        delegate(
            "click",
            '[data-bb-toggle="gallery-reset"]',
            (event, trigger) => {
                event.preventDefault();
                const list = trigger.closest(".list-images");
                list?.querySelectorAll(".gallery-image-item-handler").forEach(
                    (item) => item.remove(),
                );
                visible(
                    list?.querySelector(".default-placeholder-gallery-image"),
                    true,
                );
                visible(list?.querySelector(".footer-action"), false);
            },
        );
    }
    static initGalleryOrdering(list) {
        for (const item of list.querySelectorAll(".gallery-image-item-handler"))
            once(item, "gallery-order", () => {
                const controls = text("div", "", "d-flex gap-1");
                for (const [direction, caption] of [
                    [-1, "Move image earlier"],
                    [1, "Move image later"],
                ]) {
                    const button = text(
                        "button",
                        direction < 0 ? "←" : "→",
                        "btn btn-sm",
                    );
                    button.type = "button";
                    button.setAttribute("aria-label", caption);
                    button.addEventListener("click", () => {
                        const sibling =
                            direction < 0
                                ? item.previousElementSibling
                                : item.nextElementSibling;
                        if (!sibling) return;
                        direction < 0
                            ? sibling.before(item)
                            : sibling.after(item);
                        change(item.querySelector(".image-data"));
                    });
                    controls.append(button);
                }
                item.append(controls);
            });
    }
    static initEditable() {
        nodes(".editable").forEach((element) =>
            once(element, "editable", () => {
                element.setAttribute("tabindex", "0");
                element.addEventListener("click", () => {
                    if (element.querySelector("input")) return;
                    const input = document.createElement("input"),
                        previous = element.textContent;
                    input.value = element.dataset.value ?? previous;
                    input.setAttribute(
                        "aria-label",
                        element.dataset.title ??
                            element.getAttribute("aria-label") ??
                            previous,
                    );
                    element.replaceChildren(input);
                    input.focus();
                    const save = async () => {
                        if (input.value === previous) {
                            element.textContent = previous;
                            return;
                        }
                        try {
                            await request(element.dataset.url, {
                                method: "POST",
                                data: {
                                    name: element.dataset.name,
                                    pk: element.dataset.pk,
                                    value: input.value,
                                },
                            });
                            element.dataset.value = input.value;
                            element.textContent = input.value;
                        } catch (error) {
                            element.textContent = previous;
                            Kern.handleError(error);
                        }
                    };
                    input.addEventListener("keydown", (event) => {
                        if (event.key === "Enter") {
                            event.preventDefault();
                            input.blur();
                        }
                        if (event.key === "Escape") {
                            input.value = previous;
                            input.blur();
                        }
                    });
                    input.addEventListener("blur", save, { once: true });
                });
                element.addEventListener("keydown", (event) => {
                    if (event.key === "Enter" && event.target === element)
                        element.click();
                });
            }),
        );
    }
    static unmaskInputNumber(form, data) {
        const values = new Map();
        for (const input of nodes(
            "input.input-mask-number",
            nodes(form)[0] ?? document,
        )) {
            const separator = input.dataset.thousandsSeparator ?? ",";
            if (!input.name || input.disabled || input.matches?.(":disabled"))
                continue;
            const decimal = input.dataset.decimalSeparator || ".";
            const value = input.value
                .split(separator)
                .join("")
                .replace(decimal, ".");
            if (data instanceof FormData) {
                if (!values.has(input.name))
                    values.set(input.name, {
                        entries: data.getAll(input.name),
                        updated: new Set(),
                    });
                const group = values.get(input.name);
                const index = group.entries.findIndex(
                    (entry, index) =>
                        !group.updated.has(index) && entry === input.value,
                );
                if (index >= 0) {
                    group.entries[index] = value;
                    group.updated.add(index);
                }
            } else data[input.name] = value;
        }
        if (data instanceof FormData) {
            for (const [name, group] of values) {
                data.delete(name);
                for (const value of group.entries) data.append(name, value);
            }
        }
        return data;
    }
}

window.Kern = Kern;
if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", () => new Kern(), {
        once: true,
    });
else new Kern();
