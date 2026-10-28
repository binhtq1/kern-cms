import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { Script, runInNewContext } from 'node:vm'
import * as Vue from 'vue'

const read = (path) => readFileSync(path, 'utf8')

test('public scripts remain usable as classic scripts and are packaged with matching published files', () => {
    for (const path of [
        'core/base/js/app.js', 'core/base/js/vue-app.js', 'core/js-validation/js/js-validation.js',
        'core/table/js/table.js', 'packages/theme/js/toast.js', 'plugins/contact/js/contact-public.js',
        'plugins/cookie-consent/js/cookie-consent.js', 'plugins/custom-field/js/use-custom-fields.js',
        'plugins/gallery/js/gallery.js', 'plugins/language/js/language-public.js',
        'plugins/member/js/dashboard/activity-logs.js', 'plugins/member/js/dashboard/script.js',
    ]) {
        const source = read(`platform/${path.replace(/\/(js\/)/, '/public/$1')}`)
        assert.doesNotThrow(() => new Script(source, { filename: path }))
        assert.equal(read(`public/vendor/core/${path}`), source, path)
    }
    const spinner = read('platform/plugins/member/public/css/dashboard/activity-logs.css')
    assert.match(spinner, /half-circle-spinner/)
    assert.equal(read('public/vendor/core/plugins/member/css/dashboard/activity-logs.css'), spinner)
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

test('public validation registers its jQuery rules and date formatter', () => {
    const ready = []
    function jQuery(value) {
        if (typeof value === 'function') ready.push(value)
        return { ready() {}, on() {}, data() {} }
    }
    jQuery.fn = {}
    jQuery.expr = { ':': {}, pseudos: {} }
    jQuery.extend = (...values) => {
        if (typeof values[0] === 'boolean') values.shift()
        return Object.assign(...values)
    }
    jQuery.each = (values, callback) => Object.keys(values).forEach((key) => callback(key, values[key]))
    const context = { jQuery, $: jQuery, document: {}, console }
    context.window = context.self = context

    runInNewContext(read('platform/core/js-validation/public/js/js-validation.js'), context)
    ready.forEach((callback) => callback())

    assert.equal(typeof jQuery.fn.validate, 'function')
    assert.equal(typeof jQuery.validator.methods.laravelValidation, 'function')
    assert.equal(typeof context.DateFormatter, 'function')
})

test('cookie consent registers its click handler and persists consent', () => {
    const values = { 'site-cookie-name': 'consent', 'site-cookie-domain': 'example.test', 'site-cookie-lifetime': 30, 'site-session-secure': ';secure' }
    const document = { cookie: '' }
    let click
    let visible = false
    let hidden = false
    const dialog = { addClass() { visible = true }, hide() { hidden = true } }
    const $ = (target) => {
        if (typeof target === 'function') return target()
        if (target === document) return { on(_event, _selector, handler) { click = handler } }
        if (target === '.js-cookie-consent') return dialog
        return { data(key) { return values[key] } }
    }
    const context = { window: {}, document, $ }
    runInNewContext(read('platform/plugins/cookie-consent/public/js/cookie-consent.js'), context)
    assert.equal(visible, true)
    assert.equal(typeof click, 'function')
    click()
    assert.match(document.cookie, /^consent=1;expires=/)
    assert.match(document.cookie, /;domain=example\.test;path=\/;secure$/)
    assert.equal(hidden, true)
})

test('member activity logs register with the existing Vue runtime and load their data', async () => {
    let component
    let requestedUrl
    const context = {
        Vue,
        vueApp: { booting(callback) { callback({ component(_name, value) { component = value } }) } },
        axios: { get(url) { requestedUrl = url; return Promise.resolve({ data: { data: [], meta: { total: 0 } } }) } },
    }
    runInNewContext(read('platform/plugins/member/public/js/dashboard/activity-logs.js'), context)
    assert.ok(component)
    const state = component.data()
    component.methods.getActivityLogs.call(state)
    assert.equal(state.loading, true)
    await Promise.resolve()
    assert.equal(requestedUrl, '/ajax/members/activity-logs')
    assert.equal(state.loading, false)
    assert.equal(state.activityLogs.meta.total, 0)
})
