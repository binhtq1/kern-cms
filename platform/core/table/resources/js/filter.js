const requests = new WeakMap()
let nextInputId = 0
async function loadInput(select) {
    const form = select.closest('.wrapper-filter')
    const row = select.closest('.filter-item')
    const endpoint = form?.querySelector('.filter-data-url')?.value
    requests.get(select)?.abort()
    requests.delete(select)
    if (!endpoint || !row) return
    if (!select.value) {
        const input = document.createElement('input')
        input.type = 'text'
        input.className = 'form-control filter-column-value'
        input.name = 'filter_values[]'
        input.setAttribute('aria-label', 'Filter value')
        row.querySelector('.filter-column-value-wrap')?.replaceChildren(input)
        return
    }
    const controller = new AbortController()
    requests.set(select, controller)
    const url = new URL(endpoint, location.href)
    url.searchParams.set('class', form.querySelector('.filter-data-class')?.value || '')
    url.searchParams.set('key', select.value)
    url.searchParams.set('value', row.querySelector('.filter-column-value')?.value || '')
    try {
        const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        const result = await response.json()
        if (!response.ok || result.error) throw new Error(result.message || response.statusText)
        if (controller.signal.aborted) return
        row.querySelector('.filter-column-value-wrap').innerHTML = result.html || ''
        const input = row.querySelector('input.filter-column-value[type="text"]')
        if (input && result.data) {
            const datalist = document.createElement('datalist')
            datalist.id = `kern-filter-options-${++nextInputId}`
            for (const label of Object.values(result.data)) {
                const option = document.createElement('option')
                option.value = String(label)
                datalist.append(option)
            }
            input.setAttribute('list', datalist.id)
            input.after(datalist)
        }
        window.Kern?.initResources()
    } catch (error) { if (error.name !== 'AbortError') window.Kern?.showError(error.message) }
}
function reload(form, reset = false) {
    const url = new URL(location.href)
    for (const key of Array.from(url.searchParams.keys())) if (key.startsWith('filter_')) url.searchParams.delete(key)
    if (!reset) {
        const indexes = {}
        for (const [name, value] of new FormData(form)) {
            const key = name.endsWith('[]') ? name.slice(0, -2) + `[${indexes[name] || 0}]` : name
            if (name.endsWith('[]')) indexes[name] = (indexes[name] || 0) + 1
            url.searchParams.set(key, value)
        }
    }
    history.replaceState({}, '', url)
    const table = form.closest('.table-wrapper')?.querySelector('table')
    const controller = window.KernTables?.get(table?.id)
    if (controller) { controller.start = 0; controller.load() }
    form.querySelectorAll('[data-bb-toggle="datatable-reset-filter"]').forEach((button) => { button.style.display = reset ? 'none' : '' })
}
document.addEventListener('change', (event) => {
    if (event.target.matches('form.filter-form .filter-column-key')) loadInput(event.target)
})
document.addEventListener('submit', (event) => {
    if (!event.target.matches('form.filter-form')) return
    event.preventDefault()
    reload(event.target)
})
document.addEventListener('click', (event) => {
    const button = event.target.closest('.add-more-filter, .btn-remove-filter-item, [data-bb-toggle="datatable-reset-filter"]')
    const form = button?.closest('form.filter-form')
    if (!form) return
    event.preventDefault()
    if (button.matches('.add-more-filter')) {
        const template = form.closest('.wrapper-filter').querySelector('.sample-filter-item-wrap')
        const fields = form.querySelector('.filter-items-wrap')
        if (template && fields) {
            fields.insertAdjacentHTML('beforeend', template.innerHTML)
            window.Kern?.initResources()
            const select = fields.lastElementChild?.querySelector('.filter-column-key')
            if (select?.value) loadInput(select)
            select?.focus()
        }
    } else if (button.matches('.btn-remove-filter-item')) button.closest('.filter-item')?.remove()
    else {
        form.querySelectorAll('.form-filter:not(.filter-item-default)').forEach((row) => row.remove())
        form.querySelectorAll('.filter-column-key, .filter-column-value').forEach((input) => { input.value = '' })
        form.querySelectorAll('.filter-column-operator').forEach((input) => { input.value = '=' })
        reload(form, true)
    }
})
const initialize = () => document.querySelectorAll('form.filter-form .filter-column-key').forEach((select) => { if (select.value) loadInput(select) })
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
