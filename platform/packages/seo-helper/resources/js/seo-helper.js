const setText = (selector, value) => document.querySelectorAll(selector).forEach((element) => { element.textContent = value })
const value = (selector) => document.querySelector(selector)?.value || ''
function update() {
    const title = value('#seo_title') || value('input[name="name"]') || value('input[name="title"]')
    setText('.page-title-seo', title)
    setText('.page-description-seo', value('#seo_description') || value('textarea[name="description"]'))
    document.querySelectorAll('.default-seo-description').forEach((element) => element.classList.toggle('hidden', Boolean(title)))
    document.querySelectorAll('.existed-seo-meta').forEach((element) => element.classList.toggle('hidden', !title))
    const permalink = document.querySelector('#sample-permalink a')
    if (permalink) setText('.page-url-seo p', permalink.href.replace('?preview=true', ''))
}
document.addEventListener('input', (event) => {
    if (event.target.matches('input[name="name"], input[name="title"], textarea[name="description"], #seo_title, #seo_description')) update()
})
document.addEventListener('click', (event) => {
    if (!event.target.closest('.btn-trigger-show-seo-detail')) return
    event.preventDefault()
    document.querySelectorAll('.seo-edit-section').forEach((element) => element.classList.toggle('hidden'))
})
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', update, { once: true })
else update()
