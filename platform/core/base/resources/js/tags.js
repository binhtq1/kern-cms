const initialized = new WeakSet()
const readBoolean = (element, name, fallback) => element.dataset[name] === undefined ? fallback : element.dataset[name] !== 'false'
function initialize() {
    if (!window.Tagify) return
    document.querySelectorAll('.tags, .list-tagify').forEach((element) => {
        if (initialized.has(element) || element.classList.contains('tagify')) return
        initialized.add(element)
        const listed = element.classList.contains('list-tagify')
        let whitelist = element.value ? element.value.trim().split(/\s*,\s*/) : []
        if (listed) {
            try { whitelist = Object.entries(JSON.parse(element.dataset.list || '{}')).map(([value, name]) => ({ value, name })) }
            catch { initialized.delete(element); return }
        }
        const tagify = new Tagify(element, {
            keepInvalidTags: readBoolean(element, 'keepInvalidTags', true),
            enforceWhitelist: listed || readBoolean(element, 'enforceWhitelist', false),
            delimiters: element.dataset.delimiters || ',',
            userInput: readBoolean(element, 'userInput', true),
            whitelist,
            ...(listed ? {
                tagTextProp: 'name', skipInvalid: true,
                dropdown: { closeOnSelect: false, enabled: 0, searchKeys: ['value', 'name'] },
                originalInputValueFormat: (values) => values.map((item) => item.value).join(','),
            } : {}),
        })
        if (listed) tagify.loadOriginalValues(whitelist.filter((item) => element.value.split(',').includes(String(item.value))))
        let controller
        if (element.dataset.url) tagify.on('input', async (event) => {
            controller?.abort()
            controller = new AbortController()
            const request = controller
            tagify.loading(true)
            try {
                const response = await fetch(element.dataset.url, { signal: request.signal, headers: { Accept: 'application/json' } })
                if (!response.ok) throw new Error(response.statusText)
                const data = await response.json()
                if (request.signal.aborted) return
                tagify.settings.whitelist = Array.isArray(data) ? data : data.data || []
                tagify.dropdown.show(event.detail.value)
            } catch (error) { if (error.name !== 'AbortError') window.Kern?.showError(error.message) }
            finally { if (controller === request) tagify.loading(false) }
        })
    })
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
document.addEventListener('core-init-resources', initialize)
