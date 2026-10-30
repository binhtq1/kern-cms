const menuButton = document.querySelector('[data-menu-toggle]')
const navigation = document.querySelector('[data-site-navigation]')

const closeNavigation = () => {
    if (!menuButton || !navigation) return

    menuButton.setAttribute('aria-expanded', 'false')
    navigation.classList.remove('is-open')
}

if (menuButton && navigation) {
    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true'
        menuButton.setAttribute('aria-expanded', String(!isOpen))
        navigation.classList.toggle('is-open', !isOpen)
    })

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNavigation()
            menuButton.focus()
        }
    })
}

document.querySelectorAll('[data-submenu-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const isOpen = button.getAttribute('aria-expanded') === 'true'
        const submenu = button.nextElementSibling

        button.setAttribute('aria-expanded', String(!isOpen))
        submenu?.classList.toggle('is-open', !isOpen)
    })
})
