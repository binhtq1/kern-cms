const initialized = new WeakSet()
const formRequests = new WeakMap()
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || ''
async function request(url, method = 'GET', body, signal) {
    const response = await fetch(url, {
        method, signal, body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}) },
    })
    const result = await response.json()
    if (!response.ok || result.error) {
        window.Kern?.handleValidationError(result.errors || {})
        throw new Error(result.message || response.statusText)
    }
    return result
}
const serialize = (list) => Array.from(list?.children || []).filter((row) => row.matches('.dd-item')).map((row) => {
    const children = serialize(row.querySelector(':scope > .dd-list'))
    return { id: row.dataset.id, ...(children.length ? { children } : {}) }
})
function initialize() {
    document.querySelectorAll('.file-tree-wrapper .dd').forEach((tree) => {
        tree.querySelectorAll('.dd-list').forEach((list) => {
            if (initialized.has(list)) return
            initialized.add(list)
            if (window.Sortable) new Sortable(list, {
                group: 'kern-category-tree', draggable: '.dd-item', handle: '.dd-handle', animation: 150,
                onEnd: () => persist(tree),
                onMove: (event) => {
                    let depth = 1
                    for (let parent = event.to; parent && parent !== tree; parent = parent.parentElement) if (parent.matches('.dd-item')) depth++
                    return depth <= Number(tree.dataset.depth || 5)
                },
            })
        })
        tree.querySelectorAll('.dd-item').forEach((row) => {
            const content = row.querySelector(':scope > .dd3-content, :scope > .dd-handle')
            if (!content || content.querySelector('[data-tree-move]')) return
            for (const [direction, label] of [['up', 'Move up'], ['down', 'Move down'], ['in', 'Indent'], ['out', 'Outdent']]) {
                const button = document.createElement('button')
                button.type = 'button'
                button.className = 'btn btn-sm'
                button.dataset.treeMove = direction
                button.textContent = ({ up: '↑', down: '↓', in: '→', out: '←' })[direction]
                button.setAttribute('aria-label', label)
                content.append(button)
            }
        })
    })
}
async function persist(tree) {
    const wrapper = tree.closest('.file-tree-wrapper')
    if (!wrapper?.dataset.updateUrl) return
    window.Kern?.showLoading(tree)
    try {
        const result = await request(wrapper.dataset.updateUrl, 'PUT', { data: serialize(tree.querySelector(':scope > .dd-list')) })
        window.Kern?.showSuccess(result.message)
    } catch (error) {
        window.Kern?.showError(error.message)
        await reloadTree(wrapper)
    } finally { window.Kern?.hideLoading(tree) }
}
async function reloadTree(wrapper, activeId) {
    const result = await request(wrapper.dataset.url || window.location.href)
    wrapper.innerHTML = result.data || ''
    initialize()
    const target = Array.from(wrapper.querySelectorAll('.dd-item')).find((row) => String(row.dataset.id) === String(activeId))?.querySelector('.fetch-data')
    if (target) target.click()
}
async function loadForm(url, active) {
    const container = document.querySelector('.tree-form-container')
    if (!container) return
    formRequests.get(container)?.abort()
    const controller = new AbortController()
    formRequests.set(container, controller)
    window.Kern?.showLoading(container)
    try {
        document.querySelectorAll('.file-tree-wrapper .dd3-content.active').forEach((row) => row.classList.remove('active'))
        active?.classList.add('active')
        const result = await request(url, 'GET', undefined, controller.signal)
        if (controller.signal.aborted) return
        const target = document.querySelector('.tree-form-body')
        if (target) target.innerHTML = result.data || ''
        initialize()
        window.Kern?.initResources()
        window.Kern?.initMediaIntegrate()
        window.EDITOR?.init()
    } catch (error) { if (error.name !== 'AbortError') window.Kern?.showError(error.message) }
    finally { if (formRequests.get(container) === controller) window.Kern?.hideLoading(container) }
}
document.addEventListener('click', (event) => {
    const move = event.target.closest('[data-tree-move]')
    if (move) {
        event.preventDefault()
        const row = move.closest('.dd-item')
        const tree = row.closest('.dd')
        const previous = row.previousElementSibling
        const next = row.nextElementSibling
        switch (move.dataset.treeMove) {
            case 'up': if (previous) previous.before(row); break
            case 'down': if (next) next.after(row); break
            case 'in': {
                let depth = 1
                for (let parent = row.parentElement; parent && parent !== tree; parent = parent.parentElement) if (parent.matches('.dd-item')) depth++
                if (!previous || depth >= Number(tree.dataset.depth || 5)) return
                let list = previous.querySelector(':scope > .dd-list')
                if (!list) { list = document.createElement('ol'); list.className = 'dd-list'; previous.append(list) }
                list.append(row); initialize(); break
            }
            case 'out': row.parentElement.closest('.dd-item')?.after(row); break
        }
        move.focus()
        persist(tree)
        return
    }
    const fetch = event.target.closest('.file-tree-wrapper .fetch-data, .tree-categories-create, #list-others-language a')
    if (!fetch) return
    event.preventDefault()
    const url = fetch.dataset.href || fetch.href
    if (url && !url.startsWith('javascript:')) {
        const target = new URL(url, location.href)
        const language = new URLSearchParams(location.search).get('ref_lang')
        if (language) target.searchParams.set('ref_lang', language)
        loadForm(target.href, fetch.closest('.dd3-content'))
    }
})
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.tree-form-container form')
    if (!form) return
    event.preventDefault()
    if (form.dataset.submitting) return
    form.dataset.submitting = 'true'
    const data = new FormData(form)
    const submitter = event.submitter
    if (submitter?.name) data.set(submitter.name, submitter.value)
    const buttons = Array.from(form.querySelectorAll('button[type="submit"]'))
    buttons.forEach((button) => { button.disabled = true })
    try {
        const result = await request(form.action, (form.method || 'POST').toUpperCase(), data)
        window.Kern?.showSuccess(result.message)
        const wrapper = document.querySelector('.file-tree-wrapper')
        const id = submitter?.value === 'apply' ? result.data?.model?.id : null
        if (wrapper) await reloadTree(wrapper, id)
        if (!id) document.querySelector('.tree-categories-create')?.click()
    } catch (error) { window.Kern?.showError(error.message) }
    finally { delete form.dataset.submitting; buttons.forEach((button) => { button.disabled = false }) }
})
document.addEventListener('show.bs.modal', (event) => {
    if (!event.target.matches('.modal-confirm-delete')) return
    const button = event.target.querySelector('[data-bb-toggle="modal-confirm-delete"]')
    if (button) button.dataset.url = event.relatedTarget?.dataset.url || ''
})
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-bb-toggle="modal-confirm-delete"]')
    if (!button?.dataset.url || !document.querySelector('.file-tree-wrapper')) return
    event.preventDefault()
    button.disabled = true
    try {
        const result = await request(button.dataset.url, 'DELETE')
        window.Kern?.showSuccess(result.message)
        await reloadTree(document.querySelector('.file-tree-wrapper'))
        document.querySelector('.tree-categories-create')?.click()
        const modal = button.closest('.modal')
        if (modal) window.bootstrap?.Modal.getInstance(modal)?.hide()
    } catch (error) { window.Kern?.showError(error.message) }
    finally { button.disabled = false }
})
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
document.addEventListener('core-init-resources', initialize)
