function closeLanguageMenus(except) {
    document.querySelectorAll('.language-wrapper').forEach((wrapper) => {
        if (wrapper === except) return
        wrapper.querySelectorAll('.dropdown-menu').forEach((menu) => {
            menu.style.display = 'none'
        })
        wrapper.querySelectorAll('.dropdown-toggle').forEach((toggle) => {
            toggle.classList.remove('active')
            toggle.setAttribute('aria-expanded', 'false')
        })
    })
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.language-wrapper .dropdown .dropdown-toggle')
    const wrapper = toggle?.closest('.language-wrapper')
    closeLanguageMenus(wrapper)
    if (!toggle) return
    event.preventDefault()
    const open = !toggle.classList.contains('active')
    toggle.classList.toggle('active', open)
    toggle.setAttribute('aria-expanded', String(open))
    wrapper.querySelectorAll('.dropdown-menu').forEach((menu) => {
        menu.style.display = open ? 'block' : 'none'
    })
    if (open) wrapper.querySelector('.dropdown-menu a')?.focus()
})
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return
    const wrapper = event.target.closest('.language-wrapper')
    closeLanguageMenus()
    wrapper?.querySelector('.dropdown-toggle')?.focus()
})
