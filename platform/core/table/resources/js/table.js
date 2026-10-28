const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || ''
const tables = new Map()
window.KernTables = tables
const readValue = (row, path) => String(path || '').split('.').reduce((value, key) => value?.[key], row)
class NativeTable {
    constructor(table, options) {
        this.element = table
        this.wrapper = table.closest('.table-wrapper') || table.parentElement
        this.options = options
        this.start = 0
        this.length = Number(options.pageLength || 10)
        this.search = ''
        this.order = options.order?.[0] || null
        this.draw = 0
        this.controller = null
        const footer = document.createElement('div')
        footer.className = 'card-footer d-flex align-items-center justify-content-between gap-2 flex-wrap'
        const length = document.createElement('select')
        length.className = 'form-select w-auto'
        length.setAttribute('aria-label', 'Rows per page')
        for (const count of [...new Set([this.length, 10, 30, 50, 100, 500])].sort((a, b) => a - b)) length.add(new Option(String(count), String(count)))
        length.add(new Option(window.KernVariables?.languages?.tables?.all || 'All', '-1'))
        length.value = String(this.length)
        length.addEventListener('change', () => { this.length = Number(length.value); this.start = 0; this.load() })
        this.previous = document.createElement('button')
        this.previous.type = 'button'
        this.previous.className = 'btn'
        this.previous.textContent = options.previous || 'Previous'
        this.previous.addEventListener('click', () => { this.start = Math.max(0, this.start - this.length); this.load() })
        this.next = document.createElement('button')
        this.next.type = 'button'
        this.next.className = 'btn'
        this.next.textContent = options.next || 'Next'
        this.next.addEventListener('click', () => { this.start += this.length; this.load() })
        this.status = document.createElement('span')
        this.status.setAttribute('role', 'status')
        footer.append(length, this.previous, this.status, this.next)
        this.wrapper.append(footer)
        table.querySelectorAll('thead th').forEach((heading, index) => {
            const column = options.columns[index]
            if (!column) return
            heading.hidden = column.visible === false
            if (column.orderable === false) return
            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'btn btn-link p-0 text-reset'
            button.textContent = heading.textContent
            heading.replaceChildren(button)
            button.addEventListener('click', () => {
                this.order = [index, this.order?.[0] === index && this.order?.[1] === 'asc' ? 'desc' : 'asc']
                this.start = 0
                this.load()
            })
        })
        this.wrapper.addEventListener('input', (event) => {
            if (!event.target.matches('.table-search-input input')) return
            clearTimeout(this.timer)
            this.timer = setTimeout(() => { this.search = event.target.value; this.start = 0; this.load() }, 250)
        })
        this.wrapper.addEventListener('change', (event) => {
            if (event.target.matches('.table-check-all')) table.querySelectorAll('.checkboxes').forEach((checkbox) => { checkbox.checked = event.target.checked })
            if (event.target.matches('.table-check-all, .checkboxes')) this.updateSelection()
        })
        this.wrapper.addEventListener('click', (event) => this.action(event))
        this.load()
    }
    params() {
        const params = new URLSearchParams(new URL(this.options.url, location.href).search)
        for (const key of Array.from(params.keys())) if (key.startsWith('filter_')) params.delete(key)
        for (const [key, value] of new URLSearchParams(location.search)) if (key.startsWith('filter_')) params.append(key, value)
        params.set('draw', String(++this.draw))
        params.set('start', String(this.start))
        params.set('length', String(this.length))
        params.set('search[value]', this.search)
        params.set('search[regex]', 'false')
        if (this.order) {
            params.set('order[0][column]', String(this.order[0]))
            params.set('order[0][dir]', this.order[1])
        }
        params.set('_token', csrf())
        this.options.columns.forEach((column, index) => {
            for (const key of ['data', 'name', 'orderable', 'searchable']) params.set(`columns[${index}][${key}]`, String(column[key] ?? (key === 'data' || key === 'name' ? '' : true)))
            params.set(`columns[${index}][search][value]`, '')
            params.set(`columns[${index}][search][regex]`, 'false')
        })
        return params
    }
    async load() {
        this.controller?.abort()
        const controller = new AbortController()
        this.controller = controller
        this.element.setAttribute('aria-busy', 'true')
        try {
            const url = new URL(this.options.url, location.href)
            for (const key of Array.from(url.searchParams.keys())) if (key.startsWith('filter_')) url.searchParams.delete(key)
            const response = await fetch(url, { method: 'POST', body: this.params(), signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() } })
            const result = await response.json()
            if (!response.ok || result.error) throw new Error(result.message || response.statusText)
            if (controller.signal.aborted) return
            const body = this.element.tBodies[0] || this.element.createTBody()
            body.replaceChildren()
            for (const row of result.data || []) {
                const tr = body.insertRow()
                for (const column of this.options.columns) {
                    const td = tr.insertCell()
                    td.hidden = column.visible === false
                    td.className = column.className || ''
                    // The endpoint escapes normal values and explicitly renders formatted columns as HTML.
                    td.innerHTML = String(readValue(row, column.data) ?? '')
                }
            }
            if (!body.rows.length) {
                const td = body.insertRow().insertCell()
                td.colSpan = this.options.columns.filter((column) => column.visible !== false).length
                td.textContent = this.options.empty || 'No records found'
            }
            this.total = Number(result.recordsFiltered ?? result.recordsTotal ?? 0)
            this.previous.disabled = this.start === 0
            this.next.disabled = this.length < 0 || this.start + this.length >= this.total
            this.status.textContent = `${this.total ? this.start + 1 : 0}–${(this.length < 0 ? this.total : Math.min(this.start + this.length, this.total))} / ${this.total}`
            this.element.querySelectorAll('thead th').forEach((heading, index) => {
                heading.setAttribute('aria-sort', this.order?.[0] === index ? this.order?.[1] === 'asc' ? 'ascending' : 'descending' : 'none')
            })
            this.updateSelection()
            window.Kern?.initResources()
            document.dispatchEvent(new CustomEvent('core-table-init-completed', { detail: { table: this } }))
        } catch (error) {
            if (error.name !== 'AbortError') { this.status.textContent = error.message; window.Kern?.showError(error.message) }
        } finally { if (this.controller === controller) this.element.removeAttribute('aria-busy') }
    }
    ids() { return Array.from(this.element.querySelectorAll('.checkboxes:checked'), (checkbox) => checkbox.value) }
    updateSelection() {
        const all = Array.from(this.element.querySelectorAll('.checkboxes'))
        const checked = all.filter((checkbox) => checkbox.checked)
        this.element.querySelectorAll('.table-check-all').forEach((checkbox) => { checkbox.checked = all.length > 0 && checked.length === all.length; checkbox.indeterminate = checked.length > 0 && checked.length < all.length })
        all.forEach((checkbox) => checkbox.closest('tr')?.classList.toggle('selected', checkbox.checked))
    }
    async mutate(url, method, data = {}) {
        const response = await fetch(url, { method: (method || 'POST').toUpperCase(), credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(data) })
        const result = await response.json()
        if (!response.ok || result.error) throw new Error(result.message || response.statusText)
        window.Kern?.showSuccess(result.message)
        await this.load()
    }
    async export(type) {
        const url = new URL(this.options.url, location.href)
        url.pathname = url.pathname.replace(/\/$/, '') + '/export'
        const body = this.params()
        body.set('action', type)
        const response = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf() } })
        if (!response.ok) throw new Error(response.statusText)
        const link = document.createElement('a')
        link.href = URL.createObjectURL(await response.blob())
        link.download = response.headers.get('Content-Disposition')?.match(/filename[^;=]*=["']?([^"';]+)/)?.[1] || `export.${type === 'excel' ? 'xlsx' : 'csv'}`
        document.body.append(link)
        link.click()
        link.remove()
        setTimeout(() => URL.revokeObjectURL(link.href), 1000)
    }
    async action(event) {
        const button = event.target.closest('a, button')
        if (!button) return
        try {
            if (button.matches('.search-icon, .search-reset-icon')) {
                event.preventDefault()
                clearTimeout(this.timer)
                const input = this.wrapper.querySelector('.table-search-input input')
                if (button.matches('.search-reset-icon')) input.value = ''
                this.search = input.value; this.start = 0; await this.load()
            } else if (button.matches('.buttons-reload, [data-bb-toggle="dt-buttons"]')) { event.preventDefault(); await this.load() }
            else if (button.matches('.btn-show-table-options')) {
                event.preventDefault()
                const panel = this.wrapper.querySelector('.table-configuration-wrap')
                if (panel) panel.style.display = getComputedStyle(panel).display === 'none' ? 'block' : 'none'
            } else if (button.matches('[data-bb-toggle="dt-exports"], .buttons-excel, .buttons-csv')) {
                event.preventDefault()
                await this.export(button.dataset.bbTarget || (button.classList.contains('buttons-excel') ? 'excel' : 'csv'))
            } else if (button.matches('.deleteDialog, [data-dt-single-action], [data-trigger-bulk-action], .delete-many-entry-trigger')) {
                event.preventDefault()
                const bulk = button.matches('[data-trigger-bulk-action], .delete-many-entry-trigger')
                const ids = this.ids()
                if (bulk && !ids.length) { window.Kern?.showError('Please select at least one record.'); return }
                const title = button.dataset.confirmationModalTitle || 'Confirm action'
                const message = button.dataset.confirmationModalMessage || 'Are you sure you want to continue?'
                if ((bulk || button.matches('.deleteDialog') || button.dataset.confirmationModal) && !window.confirm(`${title}\n${message}`)) return
                button.disabled = true
                try {
                    await this.mutate(button.dataset.section || button.href, button.dataset.method || 'DELETE', bulk ? { ids, class: button.dataset.classItem, bulk_action: 1, bulk_action_table: button.dataset.tableTarget, bulk_action_target: button.dataset.target } : {})
                } finally { button.disabled = false }
            } else if (button.matches('.bulk-change-item')) {
                event.preventDefault()
                if (!this.ids().length) { window.Kern?.showError('Please select at least one record.'); return }
                const modal = document.querySelector('.modal-bulk-change-items')
                const submit = modal?.querySelector('.confirm-bulk-change-button')
                if (!submit) return
                submit.dataset.parentTable = this.element.id
                submit.dataset.url = button.dataset.saveUrl
                submit.dataset.classItem = button.dataset.classItem
                submit.dataset.key = button.dataset.key
                const url = new URL(submit.dataset.loadUrl, location.href)
                url.searchParams.set('class', button.dataset.classItem)
                url.searchParams.set('key', button.dataset.key)
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                const result = await response.json()
                if (!response.ok) throw new Error(result.message || response.statusText)
                modal.querySelector('.modal-bulk-change-content').innerHTML = result.html || ''
                window.Kern?.initResources()
                window.bootstrap?.Modal.getOrCreateInstance(modal).show()
            }
        } catch (error) { window.Kern?.showError(error.message) }
    }
}
function initialize() {
    document.querySelectorAll('script[data-kern-table]').forEach((script) => {
        if (tables.has(script.dataset.kernTable)) return
        const element = document.getElementById(script.dataset.kernTable)
        if (element) tables.set(element.id, new NativeTable(element, JSON.parse(script.textContent)))
    })
}
document.addEventListener('click', async (event) => {
    const button = event.target.closest('.confirm-bulk-change-button')
    const table = button && tables.get(button.dataset.parentTable)
    if (!table || button.disabled) return
    event.preventDefault()
    button.disabled = true
    try {
        await table.mutate(button.dataset.url, 'POST', { ids: table.ids(), class: button.dataset.classItem, key: button.dataset.key, value: button.closest('.modal').querySelector('.input-value')?.value || '' })
        window.bootstrap?.Modal.getInstance(button.closest('.modal'))?.hide()
    } catch (error) { window.Kern?.showError(error.message) }
    finally { button.disabled = false }
})
document.addEventListener('change', async (event) => {
    if (!event.target.matches('[data-bb-toggle="dt-columns-visibility-toggle"]')) return
    const form = event.target.closest('form')
    const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
    if (response.ok) location.reload()
    else window.Kern?.showError('Could not save column visibility.')
})
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
document.addEventListener('core-init-resources', initialize)
