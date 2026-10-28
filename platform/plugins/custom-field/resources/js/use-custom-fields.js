const data = new WeakMap()
const initialized = new WeakSet()
let nextId = 0
const clone = (value) => JSON.parse(JSON.stringify(value))
const escape = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character],
    )

export class Helpers {
    static wysiwyg(elements) {
        for (const element of elements) {
            element.id ||= 'custom-field-editor-' + ++nextId
            const type = window.tinymce ? 'tinymce' : 'ckeditor'
            element.classList.add('editor-' + type)
            window.requestAnimationFrame(() => {
                if (window.EDITOR) window.EDITOR.initEditor([element], {}, type)
            })
        }
    }
    static wysiwygGetContent(element) {
        if (!element) return ''
        return (
            window.EDITOR?.CKEDITOR?.[element.id]?.getData() ??
            window.tinymce?.get(element.id)?.getContent() ??
            element.value
        )
    }
    static arrayGet(array, key, fallback = null) {
        return array?.[key] ?? fallback
    }
    static jsonEncode(value) {
        return JSON.stringify(value ?? null)
    }
    static jsonDecode(value, fallback = null) {
        try {
            return JSON.parse(value)
        } catch {
            return fallback
        }
    }
    static decodeBase64(value) {
        return new TextDecoder().decode(Uint8Array.from(atob(value), (character) => character.charCodeAt(0)))
    }
    static choices(value) {
        return String(value || '')
            .split('\n')
            .filter((line) => line.trim())
            .map((line) => {
                const separator = line.indexOf(':')
                return separator < 0
                    ? [line.trim(), line.trim()]
                    : [line.slice(0, separator).trim(), line.slice(separator + 1).trim()]
            })
    }
}

export class UseCustomFields {
    constructor() {
        this.container = document.getElementById('custom_fields_container')
        this.output = document.getElementById('custom_fields_json')
        if (!this.container || !this.output || initialized.has(this.container)) return
        const raw = this.output.value || this.output.textContent
        if (!raw.trim()) this.groups = []
        else {
            try {
                this.groups = JSON.parse(Helpers.decodeBase64(raw))
            } catch {
                try {
                    this.groups = JSON.parse(raw)
                } catch {
                    return
                }
            }
        }
        if (!Array.isArray(this.groups)) return
        initialized.add(this.container)
        for (const group of this.groups) {
            const element = this.template('field_group', { title: group.title })
            if (!element) continue
            data.set(element, group)
            for (const box of Array.isArray(group.items) ? group.items : [])
                this.renderBox(box, element.querySelector('.meta-boxes-body'))
            this.container.append(element)
        }
        this.container.addEventListener('click', (event) => this.click(event))
        this.container.addEventListener('input', () => this.serialize())
        this.container.addEventListener('change', () => this.serialize())
        this.output.form?.addEventListener('submit', () => this.serialize(), true)
        this.serialize()
        window.Kern?.initMediaIntegrate()
    }

    template(name, replacements = {}) {
        const source = document.getElementById('_render_custom_field_' + name + '_template')
        if (!source) return null
        const template = document.createElement('template')
        template.innerHTML = source.innerHTML.replace(/__([a-zA-Z]+)__/g, (_match, key) =>
            escape(replacements[key] ?? ''),
        )
        return template.content.firstElementChild
    }

    renderBox(box, container, repeater = false) {
        const skeleton = this.template(repeater ? 'repeater_line' : 'global_skeleton', box)
        if (!skeleton || !container) return
        data.set(skeleton, clone(box))
        const target = skeleton.querySelector(repeater ? '.repeater-item-input' : '.meta-box-wrap')
        const field = this.renderField(box)
        if (field) target.append(field)
        container.append(skeleton)
        Helpers.wysiwyg(skeleton.querySelectorAll('.wysiwyg-editor'))
    }

    renderField(box) {
        const options = box.options || {}
        const value = box.value ?? options.defaultValue ?? ''
        if (box.type === 'repeater') {
            const repeater = this.template('repeater')
            if (!repeater) return null
            data.set(repeater, clone(box))
            repeater.querySelector('.repeater-add-new-field').textContent = options.buttonLabel || 'Add new item'
            const list = repeater.querySelector('.field-group-items')
            data.set(list, clone(box.items || []))
            for (const row of Array.isArray(box.value) ? box.value : []) this.appendRow(list, row)
            if (window.Sortable) {
                new window.Sortable(list, {
                    handle: '.custom-field-sort-handle',
                    animation: 150,
                    onEnd: () => {
                        this.numberRows(list)
                        this.serialize()
                    },
                })
            }
            return repeater
        }
        const wrapper = document.createElement('div')
        wrapper.className = 'lcf-' + box.type + '-wrapper'
        if (['radio', 'checkbox'].includes(box.type)) {
            const id = ++nextId
            const choices = Helpers.choices(options.selectChoices)
            choices.forEach(([key, label], index) => {
                const element = this.template(box.type, { value: key, title: label, id })
                if (!element) return
                const input = element.querySelector('input')
                input.checked =
                    box.type === 'checkbox'
                        ? Array.isArray(value) && value.map(String).includes(key)
                        : String(value) === key || (emptyValue(value) && index === 0)
                wrapper.append(element)
            })
            return wrapper
        }
        const element = this.template(box.type === 'wysiwyg' ? 'wysiswg' : box.type, {
            value: box.type === 'wysiwyg' ? (box.value ?? options.defaultValueTextarea ?? '') : value,
            placeholderText: options.placeholderText,
            rows: options.rows || 3,
            image: box.thumb || '',
            url: box.full_url || '',
        })
        if (!element) return null
        if (box.type === 'select') {
            Helpers.choices(options.selectChoices).forEach(([key, label]) => {
                const option = document.createElement('option')
                option.value = key
                option.textContent = label
                element.append(option)
            })
            element.value = value
        }
        if (box.type === 'image') {
            const image = element.querySelector('img')
            if (image) {
                image.src = box.thumb || image.dataset.default || ''
                image.removeAttribute('data-src')
            }
        }
        wrapper.append(element)
        return wrapper
    }

    appendRow(list, row) {
        const element = this.template('repeater_item', { position: list.children.length + 1 })
        if (!element) return
        const fields = element.querySelector('.field-group')
        for (const box of row || []) this.renderBox(box, fields, true)
        const move = document.createElement('div')
        move.className = 'custom-field-order-controls d-flex gap-1 justify-content-end'
        const handle = document.createElement('button')
        handle.type = 'button'
        handle.className = 'custom-field-sort-handle btn btn-sm'
        handle.textContent = '↕'
        handle.setAttribute('aria-label', 'Drag to reorder item')
        handle.style.minHeight = '44px'
        handle.style.minWidth = '44px'
        move.append(handle)
        for (const [offset, label] of [
            [-1, 'Move item up'],
            [1, 'Move item down'],
        ]) {
            const button = document.createElement('button')
            button.type = 'button'
            button.textContent = offset < 0 ? '↑' : '↓'
            button.className = 'btn btn-sm'
            button.setAttribute('aria-label', label)
            button.style.minHeight = '44px'
            button.style.minWidth = '44px'
            button.addEventListener('click', () => {
                const sibling = offset < 0 ? element.previousElementSibling : element.nextElementSibling
                if (!sibling) return
                if (offset < 0) list.insertBefore(element, sibling)
                else list.insertBefore(sibling, element)
                this.numberRows(list)
                this.serialize()
                button.focus()
            })
            move.append(button)
        }
        element.prepend(move)
        list.append(element)
        this.numberRows(list)
        return element
    }

    numberRows(list) {
        ;[...list.children].forEach((row, index) => {
            row.dataset.position = String(index + 1)
        })
    }

    click(event) {
        const remove = event.target.closest('.remove-field-line')
        const collapse = event.target.closest('.collapse-field-line')
        const add = event.target.closest('.repeater-add-new-field')
        if (!remove && !collapse && !add) return
        event.preventDefault()
        if (remove) {
            const row = remove.closest('.ui-sortable-handle')
            if (!row) return
            row.querySelectorAll('textarea').forEach((textarea) => {
                window.EDITOR?.CKEDITOR?.[textarea.id]?.destroy()
                window.tinymce?.get(textarea.id)?.remove()
                if (window.EDITOR?.CKEDITOR) delete window.EDITOR.CKEDITOR[textarea.id]
            })
            const list = row.parentElement
            row.remove()
            this.numberRows(list)
        } else if (collapse) {
            const collapsed = collapse.classList.toggle('collapsed-line')
            collapse.setAttribute('aria-expanded', String(!collapsed))
            const panel = collapse.closest('.ui-sortable-handle')?.querySelector(':scope > .field-line-wrapper')
            if (panel) {
                panel.hidden = collapsed
                if (!collapsed) Helpers.wysiwyg(panel.querySelectorAll('.wysiwyg-editor'))
            }
        } else {
            const list = add.parentElement.querySelector(':scope > .field-group-items')
            if (list) this.appendRow(list, clone(data.get(list) || []))
            window.Kern?.initMediaIntegrate()
        }
        this.serialize()
    }

    readField(element) {
        const box = clone(data.get(element))
        const wrapper = element.querySelector(':scope > .meta-box-wrap, :scope > .repeater-item-input')
        if (box.type === 'repeater') {
            const list = wrapper.querySelector(':scope > .lcf-repeater > .field-group-items')
            box.value = [...list.children].map((row) =>
                [...row.querySelector(':scope > .field-line-wrapper > .field-group').children]
                    .filter((field) => data.has(field))
                    .map((field) => this.readField(field)),
            )
        } else if (box.type === 'checkbox')
            box.value = [...wrapper.querySelectorAll('input:checked')].map((input) => input.value)
        else if (box.type === 'radio') box.value = wrapper.querySelector('input:checked')?.value ?? ''
        else if (box.type === 'wysiwyg') box.value = Helpers.wysiwygGetContent(wrapper.querySelector('textarea'))
        else box.value = wrapper.querySelector('input:not([type="file"]), textarea, select')?.value ?? box.value ?? ''
        return box
    }

    serialize() {
        this.output.value = Helpers.jsonEncode(
            [...this.container.children]
                .filter((element) => data.has(element))
                .map((element) => {
                    const group = clone(data.get(element))
                    group.items = [...element.querySelector(':scope > .meta-boxes-body').children]
                        .filter((field) => data.has(field))
                        .map((field) => this.readField(field))
                    return group
                }),
        )
        return this.output.value
    }
}

const emptyValue = (value) => value === null || value === undefined || value === ''
const initialize = () => new UseCustomFields()
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
document.addEventListener('core-init-resources', initialize)
document.addEventListener('core-editor-init', () => {
    window.requestAnimationFrame(() =>
        Helpers.wysiwyg(document.querySelectorAll('#custom_fields_container .wysiwyg-editor')),
    )
})
