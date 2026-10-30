function setDrawer(open) {
    document.querySelectorAll('.ps-drawer--mobile, .ps-site-overlay').forEach((element) => element.classList.toggle('active', open))
    document.querySelectorAll('.navbar-toggler').forEach((button) => button.setAttribute('aria-expanded', String(open)))
}
document.addEventListener('click', (event) => {
    if (event.target.closest('.navbar-toggler')) setDrawer(true)
    else if (event.target.closest('.ps-drawer__close, .ps-site-overlay')) setDrawer(false)
})
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setDrawer(false) })
