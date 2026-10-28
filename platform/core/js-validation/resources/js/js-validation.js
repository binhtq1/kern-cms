import DateFormatter from './php-date-formatter/php-date-formatter.js'
import { strtotime } from './phpjs/strtotime.js'

window.DateFormatter = DateFormatter

const registrations = []
const forms = new WeakMap()
let errorId = 0
const empty = (value) =>
    value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)
const fieldName = (name) => name.replace(/\.([^.]+)/g, '[$1]')
const wildcard = (pattern) =>
    new RegExp(
        '^' +
            pattern
                .split('*')
                .map((part) => part.replace(/[.*+?^{}()|[\]\\$]/g, '\\$&'))
                .join('[^\\[\\]]*') +
            '$',
    )
const controls = (form) =>
    [...form.elements].filter(
        (field) => field.name && !field.disabled && !['submit', 'button', 'reset'].includes(field.type),
    )

function valueOf(field) {
    const peers = controls(field.form).filter((other) => other.name === field.name)
    if (field.type === 'radio') return peers.find((other) => other.checked)?.value ?? ''
    if (field.type === 'checkbox')
        return field.name.endsWith('[]')
            ? peers.filter((other) => other.checked).map((other) => other.value)
            : field.checked
              ? field.value
              : ''
    if (field.type === 'file') return [...field.files]
    if (field.multiple) return [...field.selectedOptions].map((option) => option.value)
    return field.value.trim()
}

function signatureOf(form) {
    return JSON.stringify(
        controls(form).map((field) => [
            field.name,
            field.type,
            field.type === 'file'
                ? [...field.files].map((file) => [file.name, file.size, file.lastModified])
                : valueOf(field),
        ]),
    )
}
function rulesFor(state, name) {
    return Object.entries(state.rules)
        .filter(([pattern]) => wildcard(pattern).test(name))
        .map(([, rule]) => rule)
}
function dependent(field, name) {
    name = String(name ?? '')
    let index = 0
    const indices = [...field.name.matchAll(/\[([^\]]*)\]/g)].map((match) => match[1])
    const pattern = name.replace(/\*/g, () => indices[index++] ?? '*')
    const candidates = controls(field.form).filter((other) => wildcard(pattern).test(other.name))
    return candidates.length ? valueOf(candidates[0]) : ''
}
function numericRules(state, field) {
    return rulesFor(state, field.name).some((group) =>
        group.laravelValidation?.some(([rule]) => ['Numeric', 'Integer'].includes(rule)),
    )
}
function size(state, field, value) {
    if (field.type === 'file') return field.files[0] ? field.files[0].size / 1024 : 0
    if (Array.isArray(value)) return value.length
    return numericRules(state, field) ? Number(value) : [...String(value)].length
}
function dateValue(value, field, state) {
    const dateRule =
        field &&
        rulesFor(state, field.name)
            .flatMap((group) => group.laravelValidation ?? [])
            .find(([rule]) => rule === 'DateFormat')
    if (dateRule) {
        const date = new DateFormatter().parseDate(value, dateRule[1][0])
        return date instanceof Date && !Number.isNaN(date.getTime()) ? date.getTime() / 1000 : false
    }
    return strtotime(value)
}
async function dimensions(field, params) {
    if (!field.files?.[0]) return false
    const url = URL.createObjectURL(field.files[0])
    try {
        const image = new Image()
        await new Promise((resolve, reject) => {
            image.onload = resolve
            image.onerror = reject
            image.src = url
        })
        const { naturalWidth: width, naturalHeight: height } = image
        const ratioParts = String(params.ratio ?? '')
            .split('/')
            .map(Number)
        const ratio = ratioParts.length === 2 ? ratioParts[0] / ratioParts[1] : ratioParts[0]
        return (
            (!params.width || Number(params.width) === width) &&
            (!params.height || Number(params.height) === height) &&
            (!params.min_width || width >= Number(params.min_width)) &&
            (!params.max_width || width <= Number(params.max_width)) &&
            (!params.min_height || height >= Number(params.min_height)) &&
            (!params.max_height || height <= Number(params.max_height)) &&
            (!params.ratio || Math.abs(width / height - ratio) < 0.000001)
        )
    } catch {
        return false
    } finally {
        URL.revokeObjectURL(url)
    }
}

async function checkRule(rule, value, field, params, state) {
    const present = !empty(value)
    const target = (name) => dependent(field, name)
    const otherPresent = Array.isArray(params) ? params.map((name) => !empty(target(name))) : []
    switch (rule) {
        case 'Bail':
        case 'Nullable':
        case 'Sometimes':
            return true
        case 'Required':
        case 'Filled':
            return present
        case 'RequiredIf':
            return (
                !params
                    .slice(1)
                    .map(String)
                    .includes(String(target(params[0]))) || present
            )
        case 'RequiredUnless':
            return (
                params
                    .slice(1)
                    .map(String)
                    .includes(String(target(params[0]))) || present
            )
        case 'RequiredWith':
            return !otherPresent.some(Boolean) || present
        case 'RequiredWithAll':
            return !otherPresent.every(Boolean) || present
        case 'RequiredWithout':
            return otherPresent.every(Boolean) || present
        case 'RequiredWithoutAll':
            return otherPresent.some(Boolean) || present
        case 'Accepted':
            return ['yes', 'on', '1', 'true'].includes(String(value).toLowerCase())
        case 'Confirmed':
        case 'Same':
            return String(value) === String(target(params[0]))
        case 'Different':
            return String(value) !== String(target(params[0]))
        case 'InArray':
            return controls(field.form)
                .filter((other) => wildcard(params[0]).test(other.name))
                .some((other) => String(valueOf(other)) === String(value))
        case 'Distinct':
            return !controls(field.form).some(
                (other) =>
                    other.name !== field.name &&
                    wildcard(params[0]).test(other.name) &&
                    String(valueOf(other)) === String(value),
            )
        case 'Array':
            return Array.isArray(value) || field.name.includes('[')
        case 'Boolean':
            return ['0', '1', 'true', 'false'].includes(String(value).toLowerCase())
        case 'Integer':
            return /^-?\d+$/.test(value)
        case 'Numeric':
            return value !== '' && Number.isFinite(Number(value))
        case 'String':
            return typeof value === 'string'
        case 'Digits':
            return /^\d+$/.test(value) && value.length === Number(params[0])
        case 'DigitsBetween':
            return /^\d+$/.test(value) && value.length >= Number(params[0]) && value.length <= Number(params[1])
        case 'Size':
            return size(state, field, value) === Number(params[0])
        case 'Min':
            return size(state, field, value) >= Number(params[0])
        case 'Max':
            return size(state, field, value) <= Number(params[0])
        case 'Between':
            return size(state, field, value) >= Number(params[0]) && size(state, field, value) <= Number(params[1])
        case 'In':
            return (Array.isArray(value) ? value : [value]).every((item) => params.map(String).includes(String(item)))
        case 'NotIn':
            return !(Array.isArray(value) ? value : [value]).some((item) => params.map(String).includes(String(item)))
        case 'Email':
            return /^[^\s@]+@[^\s@]+$/.test(value)
        case 'Url':
            try {
                return ['http:', 'https:', 'ftp:'].includes(new URL(value).protocol)
            } catch {
                return false
            }
        case 'Ip': {
            if (String(value).includes(':')) {
                try {
                    return new URL('http://[' + value + ']').hostname.length > 0
                } catch {
                    return false
                }
            }
            return /^(\d{1,3}\.){3}\d{1,3}$/.test(value) && value.split('.').every((part) => Number(part) <= 255)
        }
        case 'File':
            return field.files?.length > 0
        case 'Image':
            return [...(field.files ?? [])].every((file) => /\.(jpe?g|png|gif|bmp|svg|webp|avif)$/i.test(file.name))
        case 'Mimes':
            return [...(field.files ?? [])].every((file) =>
                params.map((extension) => extension.toLowerCase()).includes(file.name.split('.').pop().toLowerCase()),
            )
        case 'Mimetypes':
            return [...(field.files ?? [])].every((file) =>
                params.some((type) =>
                    type.endsWith('/*') ? file.type.startsWith(type.slice(0, -1)) : type === file.type,
                ),
            )
        case 'Dimensions':
            return dimensions(field, params)
        case 'Alpha':
            return /^(?:\p{L}|\p{M})+$/u.test(value)
        case 'AlphaNum':
            return /^(?:\p{L}|\p{M}|\p{N})+$/u.test(value)
        case 'AlphaDash':
            return /^(?:\p{L}|\p{M}|\p{N}|_|-)+$/u.test(value)
        case 'Regex': {
            const expression = params[0]
            const delimiter = expression[0]
            const end = expression.lastIndexOf(delimiter)
            try {
                return new RegExp(expression.slice(1, end), expression.slice(end + 1).replace(/[^imsu]/g, '')).test(
                    value,
                )
            } catch {
                return true
            }
        }
        case 'Date':
            return strtotime(value) !== false
        case 'DateFormat': {
            const formatter = new DateFormatter()
            const date = formatter.parseDate(value, params[0])
            return Boolean(date) && formatter.formatDate(date, params[0]) === value
        }
        case 'Before':
        case 'After': {
            const comparison = Number.isFinite(Number(params[0]))
                ? Number(params[0])
                : dateValue(
                      target(params[0]),
                      controls(field.form).find((other) => other.name === params[0]),
                      state,
                  )
            const time = dateValue(value, field, state)
            return time !== false && comparison !== false && (rule === 'Before' ? time < comparison : time > comparison)
        }
        case 'Timezone':
            try {
                new Intl.DateTimeFormat('en', { timeZone: value })
                return true
            } catch {
                return false
            }
        case 'Json':
            try {
                JSON.parse(value)
                return true
            } catch {
                return false
            }
        default:
            return true
    }
}

function clearField(field) {
    const id = field.dataset.kernErrorId
    if (id) {
        document.getElementById(id)?.remove()
        const descriptions = (field.getAttribute('aria-describedby') || '')
            .split(' ')
            .filter((item) => item && item !== id)
        if (descriptions.length) field.setAttribute('aria-describedby', descriptions.join(' '))
        else field.removeAttribute('aria-describedby')
    }
    field.removeAttribute('aria-invalid')
    field.classList.remove('is-invalid')
    delete field.dataset.kernErrorId
}
function showErrors(form, errors) {
    for (const [name, messages] of Object.entries(errors)) {
        const field = controls(form).find(
            (field) => field.name === fieldName(name) || field.name === fieldName(name) + '[]',
        )
        if (!field) continue
        clearField(field)
        const error = document.createElement('div')
        error.id = 'kern-validation-error-' + ++errorId
        error.className = 'invalid-feedback'
        error.style.display = 'block'
        error.setAttribute('role', 'alert')
        error.textContent = Array.isArray(messages) ? messages[0] : messages
        field.dataset.kernErrorId = error.id
        field.setAttribute('aria-invalid', 'true')
        field.setAttribute(
            'aria-describedby',
            [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '),
        )
        field.classList.add('is-invalid')
        const container = field.closest('[data-bb-toggle="tree-checkboxes"], .input-group')
        ;(container || field).insertAdjacentElement('afterend', error)
    }
}
function ignored(field, options) {
    if (field.type === 'hidden') return true
    const ignore = (options.ignore ?? '')
        .replace(/:hidden/g, '')
        .replace(/^\s*,|,\s*$/g, '')
        .trim()
    if (!ignore) return false
    try {
        return field.matches(ignore)
    } catch {
        return false
    }
}
async function remote(form, field, rules, state) {
    const implicit = rules.some((parameters) => parameters[3])
    if (!implicit && empty(valueOf(field))) return true
    const token = rules[0][1][1]
    const data = new FormData(form)
    data.set('_js_validation', field.name)
    data.set('_js_validation_validate_all', String(Boolean(rules[0][1][2])))
    const method = (form.method || 'POST').toUpperCase()
    const url = new URL(form.action, window.location.href)
    if (method === 'GET')
        for (const [name, value] of data) if (typeof value === 'string') url.searchParams.append(name, value)
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN':
            form.querySelector('[name="_token"]')?.value ??
            document.querySelector('meta[name="csrf-token"]')?.content ??
            '',
    }
    if (token) headers['X-XSRF-TOKEN'] = token
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
            ...(method === 'GET' ? {} : { body: data }),
        })
        const result = await response.json()
        if (response.ok && (result === true || result === 'true')) return true
        return (Array.isArray(result) && result[0]) || result.errors?.[field.name]?.[0] || result.message || rules[0][2]
    } catch {
        return state.networkMessage ?? 'Unable to validate this field. Please try again.'
    }
}
async function runValidation(form, state) {
    const signature = signatureOf(form)
    const fields = controls(form)
    fields.forEach(clearField)
    const errors = {}
    const seen = new Set()
    for (const field of fields) {
        if (seen.has(field.name) || ignored(field, state)) continue
        seen.add(field.name)
        const value = valueOf(field)
        for (const group of rulesFor(state, field.name)) {
            const rules = group.laravelValidation ?? []
            for (const [rule, params, message, implicit] of rules) {
                if (
                    empty(value) &&
                    !implicit &&
                    !['Required', 'Filled', 'Confirmed', 'Accepted'].includes(rule) &&
                    !rule.startsWith('Required')
                )
                    continue
                if (!(await checkRule(rule, value, field, params, state))) {
                    errors[field.name] = message
                    break
                }
            }
            if (!errors[field.name] && group.laravelValidationRemote?.length) {
                const result = await remote(form, field, group.laravelValidationRemote, state)
                if (result !== true) errors[field.name] = result
            }
            if (errors[field.name]) break
        }
    }
    if (signature !== signatureOf(form)) return runValidation(form, state)
    showErrors(form, errors)
    if (Object.keys(errors).length && state.focusOnError !== false) {
        const first = fields.find((field) => errors[field.name])
        first?.focus()
        first?.scrollIntoView?.({
            block: 'center',
            behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
        })
    }
    return Object.keys(errors).length === 0
}
function validate(form) {
    const state = forms.get(form)
    if (!state) return Promise.resolve(true)
    if (state.pending) return state.pending
    if (state.lastSignature === signatureOf(form)) return Promise.resolve(true)
    state.pending = runValidation(form, state)
        .then((valid) => {
            state.lastSignature = valid ? signatureOf(form) : null
            return valid
        })
        .finally(() => {
            state.pending = null
        })
    return state.pending
}
function bind(form, options) {
    if (forms.has(form)) {
        const state = forms.get(form)
        Object.assign(state, options, { rules: { ...state.rules, ...options.rules } })
        state.lastSignature = null
        return
    }
    const state = { ...options, rules: options.rules ?? {}, pending: null, resubmitting: false }
    forms.set(form, state)
    form.addEventListener('input', (event) => {
        if (event.target.name) {
            state.lastSignature = null
            clearField(event.target)
        }
    })
    form.addEventListener('change', (event) => {
        if (event.target.name) {
            state.lastSignature = null
            clearField(event.target)
        }
    })
    form.addEventListener('reset', () => {
        state.lastSignature = null
        controls(form).forEach(clearField)
    })
    form.addEventListener(
        'submit',
        async (event) => {
            if (state.resubmitting) {
                state.resubmitting = false
                return
            }
            event.preventDefault()
            event.stopImmediatePropagation()
            if (state.pending) return
            const submitter = event.submitter
            if (await validate(form)) {
                state.resubmitting = true
                try {
                    form.requestSubmit(submitter?.isConnected ? submitter : undefined)
                } finally {
                    state.resubmitting = false
                }
            }
        },
        true,
    )
}
function register(selector, options) {
    registrations.push({ selector, options })
    if (document.readyState !== 'loading') document.querySelectorAll(selector).forEach((form) => bind(form, options))
}
function flush() {
    for (const script of document.querySelectorAll(
        'script[type="application/json"][data-kern-validation]:not([data-registered])',
    )) {
        try {
            const { selector, ...options } = JSON.parse(script.textContent)
            register(selector, options)
            script.dataset.registered = 'true'
        } catch (error) {
            console.error('Unable to initialize form validation.', error)
        }
    }
    for (const registration of window.KernValidationQueue?.splice(0) ?? []) {
        const { selector, ...options } = registration
        register(selector, options)
    }
    if (document.readyState !== 'loading')
        for (const { selector, options } of registrations)
            document.querySelectorAll(selector).forEach((form) => bind(form, options))
}
window.KernValidation = { register, validate, showErrors, flush }
window.addEventListener('DOMContentLoaded', flush, { once: true })
document.addEventListener('shortcode.loaded', flush)
document.addEventListener('core-init-resources', flush)
flush()
