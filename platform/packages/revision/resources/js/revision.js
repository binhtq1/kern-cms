function initialize() {
    if (typeof window.htmldiff !== 'function') return
    document.querySelectorAll('.html-diff-content').forEach((element) => {
        element.innerHTML = window.htmldiff(element.dataset.original || '', element.innerHTML)
    })
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
