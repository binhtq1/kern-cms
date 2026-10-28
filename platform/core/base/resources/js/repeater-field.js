const unescape = (value) => {
    const textarea = document.createElement('textarea')
    textarea.innerHTML = value
    return textarea.value
}
document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-target="repeater-add"]')
    const remove = event.target.closest('[data-target="repeater-remove"]')
    if (!add && !remove) return
    event.preventDefault()
    if (remove) {
        document.querySelectorAll('[data-id]').forEach((row) => {
            if (row.dataset.id === remove.dataset.id) row.remove()
        })
        return
    }
    const id = add.dataset.id
    const group = document.getElementById(`${id}_group`)
    const template = document.getElementById(`${id}_template`)
    const fields = document.getElementById(`${id}_fields`)
    if (!group || !template || !fields) return
    const index = Number(group.dataset.nextIndex || 0)
    const content = template.innerHTML.replaceAll('__key__', index).replaceAll('__fields__', unescape(fields.textContent.replaceAll('__key__', index)))
    group.insertAdjacentHTML('beforeend', content)
    group.dataset.nextIndex = String(index + 1)
    window.Kern?.initResources()
    window.Kern?.initMediaIntegrate()
    window.Kern?.initCoreIcon()
    window.EDITOR?.init()
    group.dispatchEvent(new Event('change', { bubbles: true }))
})
