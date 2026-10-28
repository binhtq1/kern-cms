const pending = new WeakMap()
const timers = new WeakMap()
async function createSlug(box, value) {
    const form = box.closest('form')
    const metadata = box.querySelector('.slug-data')
    const input = box.querySelector('input[name="slug"]')
    if (!form || !metadata?.dataset.url || !input || input.readOnly || !value.trim()) return
    pending.get(box)?.abort()
    const controller = new AbortController()
    pending.set(box, controller)
    box.setAttribute('aria-busy', 'true')
    const body = new URLSearchParams({
        value,
        slug_id: metadata.dataset.id || '0',
        model: form.querySelector('input[name="model"]')?.value || '',
        _token: form.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '',
    })
    try {
        const response = await fetch(metadata.dataset.url, {
            method: 'POST', body, signal: controller.signal, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        const result = await response.json()
        if (!response.ok || result?.error) throw new Error(result?.message || response.statusText)
        if (controller.signal.aborted) return
        const slug = typeof result === 'string' ? result : result.data?.slug || result.data || result.slug
        if (typeof slug !== 'string') throw new Error('Invalid slug response')
        input.value = slug
        input.classList.remove('is-invalid')
        input.dispatchEvent(new Event('change', { bubbles: true }))
        const current = box.querySelector('.slug-current')
        if (current) current.value = slug
        form.querySelectorAll('.page-url-seo p').forEach((element) => { element.textContent = (metadata.dataset.view || '') + slug.replace(/^\//, '') })
    } catch (error) {
        if (error.name !== 'AbortError') {
            input.classList.add('is-invalid')
            window.Kern?.showError(error.message)
        }
    } finally {
        if (pending.get(box) === controller) { pending.delete(box); box.removeAttribute('aria-busy') }
    }
}
document.addEventListener('focusout', (event) => {
    const form = event.target.closest('.js-base-form')
    if (!form) return
    form.querySelectorAll('.slug-field-wrapper').forEach((box) => {
        const input = box.querySelector('input[name="slug"]')
        if (box.dataset.fieldName === event.target.name && !input?.value) createSlug(box, event.target.value)
    })
})
document.addEventListener('input', (event) => {
    if (!event.target.matches('input[name="slug"]')) return
    const box = event.target.closest('.slug-field-wrapper')
    if (!box) return
    clearTimeout(timers.get(box))
    if (!event.target.value.trim()) { pending.get(box)?.abort(); event.target.classList.add('is-invalid'); return }
    timers.set(box, setTimeout(() => createSlug(box, event.target.value), 700))
})
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-bb-toggle="generate-slug"]')
    if (!button) return
    event.preventDefault()
    const form = button.closest('form')
    const box = button.closest('.slug-field-wrapper') || form?.querySelector('.slug-field-wrapper')
    if (!form || !box) return
    const source = Array.from(form.elements).find((element) => element.name === box.dataset.fieldName)
    if (source) createSlug(box, source.value)
})
