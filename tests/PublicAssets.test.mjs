import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { Script, runInNewContext } from 'node:vm'
import { buildSync } from 'esbuild'

const read = (path) => readFileSync(path, 'utf8')
const bundle = (path) =>
    buildSync({
        entryPoints: [path],
        bundle: true,
        format: 'iife',
        globalName: 'NativePublic',
        write: false,
        platform: 'browser',
        logLevel: 'silent',
    }).outputFiles[0].text

test('public scripts remain usable as classic scripts and are packaged with matching published files', () => {
    for (const path of [
        'core/js-validation/js/js-validation.js',
        'packages/theme/js/toast.js',
        'plugins/contact/js/contact-public.js',
        'plugins/cookie-consent/js/cookie-consent.js',
        'plugins/gallery/js/gallery.js',
        'plugins/gallery/js/object-gallery.js',
        'plugins/language/js/language-public.js',
    ]) {
        const source = read('platform/' + path.replace(/\/(js\/)/, '/public/$1'))
        assert.doesNotThrow(() => new Script(source, { filename: path }))
        assert.equal(read('public/vendor/core/' + path), source, path)
        assert.doesNotMatch(
            source,
            /\bjQuery\b|\b(?:window|globalThis)\.\$(?![\w$])/,
            path + ' must not require a DOM library',
        )
    }
})

test('theme notifications preserve an existing Theme object when their Vite bundle runs', () => {
    const existing = { marker: 'retained' }
    const context = {
        window: { Theme: existing },
        document: { createElement: () => ({}), head: { appendChild() {} } },
    }
    runInNewContext(read('platform/packages/theme/public/js/toast.js'), context)
    assert.equal(context.window.Theme, existing)
    assert.equal(context.window.Theme.marker, 'retained')
    assert.equal(typeof context.window.Theme.showNotice, 'function')
})

function validationFixture(fields, rules, extra = {}) {
    const errors = new Map()
    const field = (name, value = '', type = 'text') => {
        const attrs = new Map()
        const classes = new Set()
        return {
            name,
            value,
            type,
            dataset: {},
            disabled: false,
            checked: false,
            classList: {
                add: (name) => classes.add(name),
                remove: (name) => classes.delete(name),
                contains: (name) => classes.has(name),
            },
            getAttribute: (name) => attrs.get(name),
            setAttribute: (name, value) => attrs.set(name, value),
            removeAttribute: (name) => attrs.delete(name),
            closest: () => null,
            matches: () => false,
            insertAdjacentElement: (_position, element) => errors.set(element.id, element),
            focus() {
                this.focused = true
            },
            scrollIntoView() {},
        }
    }
    const listeners = new Map()
    const form = {
        elements: fields.map((args) => field(...args)),
        method: 'post',
        action: 'https://example.test/contact',
        addEventListener: (event, handler) => listeners.set(event, handler),
        querySelector: (selector) => (selector === '[name="_token"]' ? { value: 'csrf' } : null),
        requestSubmit() {
            this.submissions = (this.submissions || 0) + 1
        },
    }
    form.elements.forEach((element) => {
        element.form = form
    })
    const document = {
        readyState: 'complete',
        querySelectorAll: (selector) => (selector.startsWith('script[') ? [] : [form]),
        querySelector: () => ({ content: 'csrf' }),
        getElementById: (id) => errors.get(id),
        addEventListener() {},
        createElement: () => ({
            style: {},
            setAttribute() {},
            remove() {
                errors.delete(this.id)
            },
        }),
    }
    class BrowserFormData extends FormData {
        constructor(form) {
            super()
            for (const field of form.elements) this.append(field.name, field.value)
        }
    }
    const context = {
        document,
        console,
        Date,
        URL,
        Intl,
        FormData: BrowserFormData,
        addEventListener() {},
        matchMedia: () => ({ matches: true }),
        location: { href: 'https://example.test/contact' },
        ...extra,
    }
    context.window = context.self = context
    runInNewContext(bundle('platform/core/js-validation/resources/js/js-validation.js'), context)
    context.KernValidation.register('#form', { rules, focusOnError: true })
    return { context, form, errors, listeners }
}

test('native validation preserves required, confirmation, wildcard and accessible error behavior', async () => {
    const { context, form, errors } = validationFixture(
        [
            ['name', ''],
            ['password', 'secret'],
            ['password_confirmation', 'wrong'],
            ['rows[0][title]', ''],
        ],
        {
            name: { laravelValidation: [['Required', [], 'Name is required', true]] },
            password_confirmation: { laravelValidation: [['Confirmed', ['password'], 'Passwords differ', true]] },
            'rows[*][title]': { laravelValidation: [['Required', [], 'Row title is required', true]] },
        },
    )
    assert.equal(await context.KernValidation.validate(form), false)
    assert.equal(errors.size, 3)
    assert.equal(form.elements[0].getAttribute('aria-invalid'), 'true')
    assert.equal(form.elements[0].focused, true)
    assert.deepEqual(
        [...errors.values()].map((error) => error.textContent),
        ['Name is required', 'Passwords differ', 'Row title is required'],
    )
    form.elements[0].value = 'Nguyễn'
    form.elements[2].value = 'secret'
    form.elements[3].value = 'A title'
    assert.equal(await context.KernValidation.validate(form), true)
    assert.equal(errors.size, 0)
    assert.equal(form.elements[0].getAttribute('aria-invalid'), undefined)
})

test('native validation checks Unicode sizes, conditional required fields and date formats', async () => {
    const { context, form, errors } = validationFixture(
        [
            ['name', '短い'],
            ['kind', 'business'],
            ['company', ''],
            ['birthday', '31/02/2024'],
        ],
        {
            name: { laravelValidation: [['Min', ['3'], 'At least three characters', false]] },
            company: { laravelValidation: [['RequiredIf', ['kind', 'business'], 'Company is required', true]] },
            birthday: { laravelValidation: [['DateFormat', ['d/m/Y'], 'Invalid birthday', false]] },
        },
    )
    assert.equal(await context.KernValidation.validate(form), false)
    assert.equal(errors.size, 3)
    form.elements[0].value = '日本語'
    form.elements[2].value = 'A company'
    form.elements[3].value = '29/02/2024'
    assert.equal(await context.KernValidation.validate(form), true)
})

test('native validation sends remote rules with the existing token and does not submit invalid data', async () => {
    let request
    const { context, form, errors } = validationFixture(
        [['email', 'used@example.test']],
        {
            email: { laravelValidationRemote: [['Unique', ['email', 'xsrf', false], 'Email is taken', false]] },
        },
        {
            fetch: async (url, options) => {
                request = { url, options }
                return { ok: true, json: async () => ['Email is taken'] }
            },
        },
    )
    assert.equal(await context.KernValidation.validate(form), false)
    assert.equal(request.options.body.get('_js_validation'), 'email')
    assert.equal(request.options.body.get('_js_validation_validate_all'), 'false')
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf')
    assert.equal(request.options.headers['X-XSRF-TOKEN'], 'xsrf')
    assert.equal([...errors.values()][0].textContent, 'Email is taken')
    context.fetch = async () => ({ ok: true, json: async () => true })
    assert.equal(await context.KernValidation.validate(form), true)
    assert.equal(errors.size, 0)
})

test('cookie consent persists consent using native event handlers and respects existing cookies', () => {
    const listeners = new Map()
    const classes = new Set()
    const dialog = { style: {}, classList: { add: (name) => classes.add(name) }, setAttribute() {}, getAttribute() {} }
    const document = {
        readyState: 'complete',
        cookie: '',
        querySelector: () => ({
            dataset: {
                siteCookieName: 'consent',
                siteCookieDomain: 'example.test',
                siteCookieLifetime: '30',
                siteSessionSecure: ';secure',
            },
        }),
        querySelectorAll: () => [dialog],
        addEventListener: (event, callback) => listeners.set(event, callback),
    }
    const context = { window: {}, document }
    runInNewContext(bundle('platform/plugins/cookie-consent/resources/js/cookie-consent.js'), context)
    assert.equal(classes.has('cookie-consent--visible'), true)
    listeners.get('click')({ target: { closest: () => ({}) }, preventDefault() {} })
    assert.match(document.cookie, /^consent=1;expires=/)
    assert.match(document.cookie, /;domain=example\.test;path=\/;secure;SameSite=Lax$/)
    assert.equal(dialog.hidden, true)
    document.cookie = 'other=x; consent=1'
    dialog.hidden = false
    runInNewContext(bundle('platform/plugins/cookie-consent/resources/js/cookie-consent.js'), context)
    assert.equal(dialog.hidden, true)
})

test('contact form blocks duplicate submissions, displays server validation and releases its loading state', async () => {
    const listeners = new Map()
    const button = { disabled: false, classList: { add() {}, remove() {} } }
    const success = { style: {}, setAttribute() {} }
    const failure = { style: {}, setAttribute() {} }
    class Form {
        action = 'https://example.test/contact'
        parentElement = null
        matches() {
            return true
        }
        querySelector(selector) {
            return selector.includes('success') ? success : selector.includes('error') ? failure : { value: 'csrf' }
        }
        querySelectorAll() {
            return [button]
        }
        reportValidity() {
            return true
        }
        setAttribute() {}
        removeAttribute() {}
        reset() {
            this.resetCalled = true
        }
    }
    const form = new Form()
    let calls = 0
    let complete
    const context = {
        window: {},
        HTMLFormElement: Form,
        FormData: class {},
        CustomEvent: class {},
        document: { addEventListener: (event, callback) => listeners.set(event, callback), dispatchEvent() {} },
        fetch: () => {
            calls++
            return new Promise((resolve) => {
                complete = resolve
            })
        },
    }
    runInNewContext(bundle('platform/plugins/contact/resources/js/contact-public.js'), context)
    const event = { target: form, preventDefault() {}, stopPropagation() {} }
    const first = listeners.get('submit')(event)
    await listeners.get('submit')(event)
    assert.equal(calls, 1)
    assert.equal(button.disabled, true)
    complete({
        ok: false,
        statusText: 'Unprocessable Content',
        json: async () => ({ errors: { email: ['Invalid email'] } }),
    })
    await first
    assert.equal(failure.textContent, 'Invalid email')
    assert.equal(button.disabled, false)
    assert.equal(form.resetCalled, undefined)
})

test('Ripple search aborts stale requests and only presents the latest result', async () => {
    const classes = new Set()
    const attributes = new Map()
    const results = {
        innerHTML: '',
        style: {},
        setAttribute: (key, value) => attributes.set(key, value),
        removeAttribute: (key) => attributes.delete(key),
    }
    const panel = {
        dataset: { searchUrl: '/search' },
        classList: { remove: (key) => classes.delete(key), add: (key) => classes.add(key) },
    }
    const selectors = { '.search-input': {}, '.super-search': panel, '.search-result': results }
    const timers = new Map()
    const requests = []
    let timerId = 0
    const context = {
        URL,
        AbortController,
        console,
        document: {
            readyState: 'loading',
            addEventListener() {},
            querySelector: (selector) => selectors[selector],
            querySelectorAll: () => [],
        },
        location: { href: 'https://example.test/' },
        setTimeout: (callback) => {
            timers.set(++timerId, callback)
            return timerId
        },
        clearTimeout: (id) => timers.delete(id),
        fetch: (url, options) =>
            new Promise((resolve) => {
                requests.push({ url, options, resolve })
            }),
    }
    context.window = context
    runInNewContext(bundle('platform/themes/ripple/assets/js/ripple.js'), context)
    const search = new context.NativePublic.Ripple()
    search.searchFunction('old')
    const first = [...timers.values()][0]()
    search.searchFunction('new')
    assert.equal(requests[0].options.signal.aborted, true)
    const second = [...timers.values()].at(-1)()
    assert.equal(requests[1].url.searchParams.get('q'), 'new')
    requests[1].resolve({ ok: true, json: async () => ({ error: false, data: { items: '<p>Latest</p>' } }) })
    await second
    requests[0].resolve({ ok: true, json: async () => ({ error: false, data: { items: '<p>Old</p>' } }) })
    await first
    assert.equal(results.innerHTML, '<p>Latest</p>')
    assert.equal(classes.has('search-finished'), true)
    assert.equal(attributes.has('aria-busy'), false)
})

test('native validation retries with current field values after an asynchronous check and coalesces duplicate checks', async () => {
    let complete
    let calls = 0
    const { context, form } = validationFixture(
        [['email', 'old@example.test']],
        {
            email: { laravelValidationRemote: [['Unique', ['email', 'xsrf', false], 'Email is taken', false]] },
        },
        {
            fetch: () => {
                calls++
                if (calls === 1)
                    return new Promise((resolve) => {
                        complete = resolve
                    })
                return Promise.resolve({ ok: true, json: async () => true })
            },
        },
    )
    const first = context.KernValidation.validate(form)
    const duplicate = context.KernValidation.validate(form)
    assert.equal(first, duplicate)
    assert.equal(calls, 1)
    form.elements[0].value = 'new@example.test'
    complete({ ok: true, json: async () => ['Old value is taken'] })
    assert.equal(await first, true)
    assert.equal(calls, 2)
    assert.equal(await context.KernValidation.validate(form), true)
    assert.equal(calls, 2, 'a resubmitted unchanged form reuses its completed validation')
})

test('custom-field helpers preserve UTF-8, full choice labels and editor content without DOM-library objects', () => {
    const context = {
        document: { readyState: 'loading', addEventListener() {} },
        TextDecoder,
        Uint8Array,
        atob,
        window: { EDITOR: { CKEDITOR: { editor: { getData: () => '<p>Edited</p>' } } } },
    }
    runInNewContext(bundle('platform/plugins/custom-field/resources/js/use-custom-fields.js'), context)
    const { Helpers } = context.NativePublic
    const original = { title: 'Nội dung 日本語 😀', value: 0 }
    assert.deepEqual(
        JSON.parse(Helpers.decodeBase64(Buffer.from(JSON.stringify(original)).toString('base64'))),
        original,
    )
    assert.equal(
        JSON.stringify(Helpers.choices('one: First: full label\ntwo\n\nzero: 0')),
        JSON.stringify([
            ['one', 'First: full label'],
            ['two', 'two'],
            ['zero', '0'],
        ]),
    )
    assert.equal(Helpers.wysiwygGetContent({ id: 'editor', value: 'stale' }), '<p>Edited</p>')
    assert.equal(Helpers.wysiwygGetContent({ id: 'plain', value: 'Plain fallback' }), 'Plain fallback')
    assert.equal(Helpers.arrayGet({ value: 0 }, 'value', 'default'), 0)
})

function nativeElement(tag = 'div') {
    const listeners = new Map()
    const attributes = new Map()
    const classes = new Set()
    const element = {
        tagName: tag.toUpperCase(),
        children: [],
        style: {},
        dataset: {},
        listeners,
        attributes,
        classList: {
            add: (key) => classes.add(key),
            remove: (key) => classes.delete(key),
            contains: (key) => classes.has(key),
        },
        setAttribute: (key, value) => attributes.set(key, value),
        getAttribute: (key) => attributes.get(key),
        removeAttribute: (key) => attributes.delete(key),
        addEventListener: (name, callback) => listeners.set(name, callback),
        append(...children) {
            children.forEach((child) => {
                child.parentElement = element
                element.children.push(child)
            })
        },
        querySelectorAll(selector) {
            const descendants = element.children.flatMap((child) => [child, ...child.querySelectorAll(selector)])
            return descendants.filter((child) =>
                selector === 'button' ? child.tagName === 'BUTTON' : selector === 'img' && child.tagName === 'IMG',
            )
        },
        querySelector(selector) {
            return element.querySelectorAll(selector)[0]
        },
        focus() {
            this.focused = true
        },
        showModal() {
            this.open = true
        },
        close() {
            this.open = false
            listeners.get('close')?.()
        },
        getBoundingClientRect: () => ({ width: 100 }),
        scrollTo(options) {
            this.scrollLeft = options.left
        },
        contains(target) {
            return this === target || this.children.some((child) => child.contains(target))
        },
    }
    if (tag === 'template')
        Object.defineProperty(element, 'content', {
            get: () => ({ textContent: element.innerHTML.replace(/<[^>]*>/g, '') }),
        })
    return element
}

test('native photo gallery provides looping keyboard navigation, captions and focus restoration', () => {
    const items = [0, 1].map((index) => {
        const item = nativeElement()
        item.dataset = { src: '/full-' + index + '.jpg', subHtml: '<p>Caption ' + index + '</p>' }
        const image = nativeElement('img')
        image.src = '/thumb-' + index + '.jpg'
        image.alt = 'Photo ' + index
        item.append(image)
        return item
    })
    const container = nativeElement()
    container.querySelectorAll = () => items
    const body = nativeElement('body')
    const context = {
        document: {
            readyState: 'complete',
            getElementById: () => container,
            createElement: (tag) => nativeElement(tag),
            addEventListener() {},
            body,
        },
        getComputedStyle: () => ({ direction: 'rtl' }),
    }
    runInNewContext(bundle('platform/plugins/gallery/resources/js/gallery.js'), context)
    const dialog = body.children[0]
    const stage = dialog.children[0]
    items[0].listeners.get('click')({ preventDefault() {} })
    assert.equal(dialog.open, true)
    assert.equal(stage.children[1].src, '/full-0.jpg')
    assert.equal(stage.children[2].textContent, 'Caption 0')
    dialog.listeners.get('keydown')({ key: 'ArrowRight', preventDefault() {} })
    assert.equal(stage.children[1].src, '/full-1.jpg')
    dialog.listeners.get('keydown')({ key: 'ArrowRight', preventDefault() {} })
    assert.equal(stage.children[1].src, '/full-0.jpg')
    stage.children[0].children[1].listeners.get('click')()
    assert.equal(dialog.open, false)
    assert.equal(items[0].focused, true)
})

test('native carousel retains responsive slides, RTL keyboard navigation and explicit autoplay pause', () => {
    const track = nativeElement()
    const wrapper = nativeElement()
    wrapper.dataset = { singleItem: 'true', autoPlay: '4000', navigation: 'true' }
    wrapper.append(track)
    track.append(nativeElement(), nativeElement(), nativeElement())
    const intervals = new Map()
    let nextInterval = 0
    const context = {
        document: {
            readyState: 'complete',
            hidden: false,
            querySelectorAll: () => [track],
            createElement: (tag) => nativeElement(tag),
            addEventListener() {},
        },
        getComputedStyle: () => ({ direction: 'rtl' }),
        innerWidth: 500,
        addEventListener() {},
        matchMedia: () => ({ matches: false }),
        setInterval: (callback) => {
            intervals.set(++nextInterval, callback)
            return nextInterval
        },
        clearInterval: (id) => intervals.delete(id),
    }
    context.window = context
    runInNewContext(bundle('platform/plugins/gallery/resources/js/object-gallery.js'), context)
    assert.equal(track.attributes.get('aria-roledescription'), 'carousel')
    assert.match(track.style.gridAutoColumns, /\/ 1\)/)
    assert.equal(intervals.size, 1)
    track.listeners.get('keydown')({ key: 'ArrowRight', preventDefault() {} })
    assert.equal(track.scrollLeft, -224)
    assert.equal(intervals.size, 0)
    const pause = wrapper
        .querySelectorAll('button')
        .find((button) => button.attributes.get('aria-label') === 'Pause slideshow')
    pause.listeners.get('click')()
    wrapper.listeners.get('mouseleave')()
    assert.equal(intervals.size, 0, 'manual pause must survive pointer exit')
    pause.listeners.get('click')()
    assert.equal(intervals.size, 1)
})

test('native validation initializes inert rules from lazy-rendered forms without evaluating scripts', async () => {
    const { context, form, errors } = validationFixture([['name', '']], {})
    assert.equal(await context.KernValidation.validate(form), true)
    const script = {
        textContent: JSON.stringify({
            selector: '#form',
            rules: { name: { laravelValidation: [['Required', [], '<strong>Name is required</strong>', true]] } },
        }),
        dataset: {},
    }
    context.document.querySelectorAll = (selector) =>
        selector.startsWith('script[') ? (script.dataset.registered ? [] : [script]) : [form]
    context.KernValidation.flush()
    assert.equal(script.dataset.registered, 'true')
    assert.equal(await context.KernValidation.validate(form), false)
    assert.equal([...errors.values()][0].textContent, '<strong>Name is required</strong>')
    context.KernValidation.flush()
    form.elements[0].value = 'A name'
    assert.equal(await context.KernValidation.validate(form), true)
})
