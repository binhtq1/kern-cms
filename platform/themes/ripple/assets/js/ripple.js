export class Ripple {
    constructor() {
        this.input = document.querySelector('.search-input')
        this.panel = document.querySelector('.super-search')
        this.toggles = [...document.querySelectorAll('.close-search')]
        this.toggle = this.toggles[0]
        this.opener = this.toggle
        this.results = document.querySelector('.search-result')
        this.timer = null
        this.controller = null
        this.generation = 0
    }

    searchFunction(keyword) {
        clearTimeout(this.timer)
        this.controller?.abort()
        const generation = ++this.generation
        if (!this.panel || !this.results) return
        this.timer = setTimeout(async () => {
            this.controller = new AbortController()
            const url = new URL(this.panel.dataset.searchUrl, window.location.href)
            url.searchParams.set('q', keyword)
            this.panel.classList.remove('search-finished')
            this.results.setAttribute('aria-busy', 'true')
            try {
                const response = await fetch(url, {
                    signal: this.controller.signal,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                const data = await response.json()
                if (generation !== this.generation) return
                if (!response.ok || data.error) this.results.textContent = data.message || response.statusText
                else {
                    this.results.innerHTML = data.data.items
                    this.panel.classList.add('search-finished')
                }
                this.results.hidden = false
                this.results.style.display = 'block'
            } catch (error) {
                if (error.name !== 'AbortError' && generation === this.generation) {
                    this.results.textContent = 'Search could not load. Please try again.'
                    this.results.hidden = false
                    this.results.style.display = 'block'
                }
            } finally {
                if (generation === this.generation) this.results.removeAttribute('aria-busy')
            }
        }, 500)
    }

    close() {
        clearTimeout(this.timer)
        this.controller?.abort()
        this.generation++
        this.panel?.classList.remove('active')
        this.toggles.forEach((toggle) => {
            toggle.classList.remove('active')
            toggle.setAttribute('aria-expanded', 'false')
        })
        if (this.results) {
            this.results.hidden = true
            this.results.style.display = 'none'
            this.results.removeAttribute('aria-busy')
        }
        document.body.classList.remove('overflow')
        this.opener?.focus()
    }

    bindActionToElement() {
        if (!this.input || !this.panel || !this.toggle || !this.results) return
        this.results.setAttribute('aria-live', 'polite')
        const toggleSearch = (event) => {
            event.preventDefault()
            if (this.panel.classList.contains('active')) return this.close()
            this.opener = event.currentTarget
            this.panel.classList.add('active')
            this.toggles.forEach((toggle) => {
                toggle.classList.add('active')
                toggle.setAttribute('aria-expanded', 'true')
            })
            document.body.classList.add('overflow')
            this.input.focus()
            if (this.input.value) this.searchFunction(this.input.value)
        }
        this.toggles.forEach((toggle) => {
            toggle.setAttribute('role', 'button')
            toggle.setAttribute('tabindex', '0')
            toggle.setAttribute('aria-label', toggle.getAttribute('aria-label') || 'Search')
            toggle.setAttribute('aria-expanded', 'false')
            toggle.addEventListener('click', toggleSearch)
            toggle.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') toggleSearch(event)
            })
        })
        this.input.addEventListener('input', () => this.searchFunction(this.input.value))
        this.panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') this.close()
        })
    }
}

function initializeThemeNavigation() {
    document.querySelectorAll('[data-background]').forEach((element) => {
        if (element.dataset.background)
            element.style.backgroundImage = 'url(' + JSON.stringify(element.dataset.background) + ')'
    })
    document.querySelectorAll('.page-header').forEach((header, index) => {
        const navigation = header.querySelector('.navigation')
        const toggle = header.querySelector('.navigation-toggle')
        if (!navigation || !toggle) return
        navigation.id ||= 'ripple-navigation-' + index
        toggle.setAttribute('role', 'button')
        toggle.tabIndex = 0
        toggle.setAttribute('aria-controls', navigation.id)
        toggle.setAttribute('aria-label', toggle.getAttribute('aria-label') || 'Toggle navigation')
        toggle.setAttribute('aria-expanded', 'false')
        const close = () => {
            navigation.classList.remove('navigation--mobile--active')
            toggle.classList.remove('navigation-toggle--active')
            toggle.setAttribute('aria-expanded', 'false')
        }
        const activate = (event) => {
            event.preventDefault()
            const open = !navigation.classList.contains('navigation--mobile--active')
            navigation.classList.toggle('navigation--mobile--active', open)
            toggle.classList.toggle('navigation-toggle--active', open)
            toggle.setAttribute('aria-expanded', String(open))
        }
        toggle.addEventListener('click', activate)
        toggle.addEventListener('keydown', (event) => {
            if (['Enter', ' '].includes(event.key)) activate(event)
        })
        navigation.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close()
                toggle.focus()
            }
        })
        const resize = () => {
            const mobile = window.innerWidth < Number(header.dataset.responsive || 991)
            navigation.classList.toggle('navigation--mobile', mobile)
            toggle.style.display = mobile ? 'block' : 'none'
            if (!mobile) close()
        }
        window.addEventListener('resize', resize)
        resize()
        if (['true', '1'].includes(header.dataset.sticky)) {
            let previous = window.scrollY
            window.addEventListener(
                'scroll',
                () => {
                    const position = window.scrollY
                    const checkpoint = Number(header.dataset.stickyCheckpoint || 200)
                    if (!position)
                        header.classList.remove('header--sticky', 'header--sticky-unpin', 'header--sticky-pin')
                    else if (position > checkpoint) {
                        header.classList.toggle('header--sticky-pin', position < previous)
                        header.classList.toggle('header--sticky-unpin', position >= previous)
                        header.classList.toggle('header--sticky', position < previous)
                        close()
                    }
                    previous = position
                },
                { passive: true },
            )
        }
    })
    document.querySelectorAll('.menu-item-has-children > a .toggle-icon').forEach((toggle) => {
        toggle.setAttribute('role', 'button')
        toggle.setAttribute('tabindex', '0')
        toggle.setAttribute('aria-label', 'Toggle submenu')
        toggle.setAttribute('aria-expanded', 'false')
    })
    const submenu = (event) => {
        const toggle = event.target.closest('.navigation--mobile .menu-item-has-children > a .toggle-icon')
        if (!toggle) return
        event.preventDefault()
        const item = toggle.closest('li')
        const menu = [...item.children].find((child) => child.classList.contains('sub-menu'))
        const open = !item.classList.contains('menu-item--active')
        ;[...item.parentElement.children]
            .filter((sibling) => sibling !== item)
            .forEach((sibling) => {
                sibling.classList.remove('menu-item--active')
                ;[...sibling.children]
                    .filter((child) => child.classList.contains('sub-menu'))
                    .forEach((child) => child.classList.remove('sub-menu--active'))
                sibling.querySelector(':scope > a .toggle-icon')?.setAttribute('aria-expanded', 'false')
            })
        item.classList.toggle('menu-item--active', open)
        menu?.classList.toggle('sub-menu--active', open)
        toggle.setAttribute('aria-expanded', String(open))
    }
    document.addEventListener('click', submenu)
    document.addEventListener('keydown', (event) => {
        if (['Enter', ' '].includes(event.key)) submenu(event)
    })
    const back = document.getElementById('back2top')
    if (back) {
        const update = () => back.classList.toggle('active', window.scrollY > 500)
        window.addEventListener('scroll', update, { passive: true })
        back.addEventListener('click', (event) => {
            event.preventDefault()
            window.scrollTo({
                top: 0,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
            })
        })
        update()
    }
    new Ripple().bindActionToElement()
}
if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', initializeThemeNavigation, { once: true })
else initializeThemeNavigation()
