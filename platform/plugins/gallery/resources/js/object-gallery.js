function initializeCarousels() {
    document.querySelectorAll('[data-slider="owl"] .owl-carousel').forEach((track) => {
        if (track.dataset.nativeCarousel) return
        track.dataset.nativeCarousel = 'true'
        const wrapper = track.parentElement
        const items = [...track.children]
        if (!items.length) return
        const settings = wrapper.dataset
        const single = settings.singleItem === 'true' || settings.singleItem === '1'
        const rtl = getComputedStyle(wrapper).direction === 'rtl'
        Object.assign(track.style, {
            display: 'grid',
            gridAutoFlow: 'column',
            overflowX: 'auto',
            scrollSnapType: 'x mandatory',
            gap: '12px',
            scrollbarWidth: 'none',
        })
        track.tabIndex = 0
        track.setAttribute('role', 'region')
        track.setAttribute('aria-roledescription', 'carousel')
        track.setAttribute('aria-label', settings.label || 'Image gallery')
        let index = 0
        let timer
        let paused = false
        let count = 1
        const pagination = document.createElement('div')
        Object.assign(pagination.style, { display: 'flex', justifyContent: 'center', gap: '8px', padding: '8px' })
        const control = (label, text, action) => {
            const button = document.createElement('button')
            button.type = 'button'
            button.setAttribute('aria-label', label)
            button.textContent = text
            Object.assign(button.style, { minWidth: '44px', minHeight: '44px', cursor: 'pointer' })
            button.addEventListener('click', action)
            return button
        }
        const updateDots = () =>
            pagination
                .querySelectorAll('button')
                .forEach((dot, position) => dot.setAttribute('aria-current', String(position === index)))
        const go = (next) => {
            index = (next + items.length) % items.length
            track.scrollTo({
                left: (rtl ? -1 : 1) * index * (items[0].getBoundingClientRect().width + 12),
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
            })
            updateDots()
        }
        const resize = () => {
            const width = window.innerWidth
            count = single
                ? 1
                : Number(
                      width <= 479
                          ? settings.mobileItems || 1
                          : width <= 768
                            ? settings.tabletItems || 2
                            : width <= 979
                              ? settings.desktopSmallItems || 3
                              : width <= 1199
                                ? settings.desktopItems || settings.items || 3
                                : settings.items || 3,
                  )
            count = Math.max(1, Math.min(count, items.length))
            track.style.gridAutoColumns = 'calc((100% - ' + (count - 1) * 12 + 'px) / ' + count + ')'
        }
        items.forEach((item, position) => {
            item.style.scrollSnapAlign = 'start'
            item.setAttribute('role', 'group')
            item.setAttribute('aria-roledescription', 'slide')
            item.setAttribute('aria-label', position + 1 + ' / ' + items.length)
            pagination.append(control('Go to slide ' + (position + 1), '•', () => go(position)))
        })
        if (settings.navigation && settings.navigation !== 'false' && settings.navigation !== '0') {
            const navigation = document.createElement('div')
            Object.assign(navigation.style, { display: 'flex', justifyContent: 'space-between' })
            navigation.append(
                control('Previous slide', '←', () => go(index - 1)),
                control('Next slide', '→', () => go(index + 1)),
            )
            wrapper.append(navigation)
        }
        if (items.length > 1) wrapper.append(pagination)
        const stop = () => {
            clearInterval(timer)
            timer = null
        }
        const start = () => {
            stop()
            const delay = settings.autoPlay === 'true' ? 4000 : Number(settings.autoPlay)
            if (
                !paused &&
                !wrapper.contains(document.activeElement) &&
                delay > 0 &&
                !window.matchMedia('(prefers-reduced-motion: reduce)').matches &&
                !document.hidden
            )
                timer = setInterval(() => go(index + 1), Math.max(1000, delay))
        }
        if ((Number(settings.autoPlay) > 0 || settings.autoPlay === 'true') && items.length > 1) {
            const pause = control('Pause slideshow', 'Pause', () => {
                if (!paused) {
                    paused = true
                    stop()
                    pause.textContent = 'Play'
                    pause.setAttribute('aria-label', 'Play slideshow')
                } else {
                    paused = false
                    start()
                    pause.textContent = 'Pause'
                    pause.setAttribute('aria-label', 'Pause slideshow')
                }
            })
            wrapper.append(pause)
        }
        track.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
            event.preventDefault()
            stop()
            go(
                event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? items.length - 1
                      : index + (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1),
            )
        })
        let drag = null
        let dragged = false
        track.style.cursor = 'grab'
        track.addEventListener('dragstart', (event) => event.preventDefault())
        track.addEventListener('pointerdown', (event) => {
            if (
                event.pointerType !== 'mouse' ||
                event.button !== 0 ||
                event.target.closest('button, input, select, textarea')
            )
                return
            stop()
            dragged = false
            drag = { id: event.pointerId, x: event.clientX, scroll: track.scrollLeft }
        })
        track.addEventListener('pointermove', (event) => {
            if (!drag || drag.id !== event.pointerId) return
            const distance = event.clientX - drag.x
            if (!dragged && Math.abs(distance) < 5) return
            dragged = true
            track.setPointerCapture?.(event.pointerId)
            track.style.cursor = 'grabbing'
            track.style.userSelect = 'none'
            track.scrollLeft = drag.scroll - distance
            event.preventDefault()
        })
        const endDrag = (event) => {
            if (!drag || drag.id !== event.pointerId) return
            track.releasePointerCapture?.(event.pointerId)
            drag = null
            track.style.cursor = 'grab'
            track.style.userSelect = ''
            start()
        }
        track.addEventListener('pointerup', endDrag)
        track.addEventListener('pointercancel', endDrag)
        track.addEventListener(
            'click',
            (event) => {
                if (!dragged) return
                event.preventDefault()
                event.stopImmediatePropagation()
                dragged = false
            },
            true,
        )
        track.addEventListener(
            'scroll',
            () => {
                index = Math.max(
                    0,
                    Math.min(
                        items.length - 1,
                        Math.round(Math.abs(track.scrollLeft) / (items[0].getBoundingClientRect().width + 12)),
                    ),
                )
                updateDots()
            },
            { passive: true },
        )
        wrapper.addEventListener('mouseenter', stop)
        wrapper.addEventListener('mouseleave', start)
        wrapper.addEventListener('focusin', stop)
        wrapper.addEventListener('focusout', (event) => {
            if (!wrapper.contains(event.relatedTarget)) start()
        })
        document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()))
        window.addEventListener('resize', resize)
        resize()
        updateDots()
        start()
    })
}
if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', initializeCarousels, { once: true })
else initializeCarousels()
document.addEventListener('shortcode.loaded', initializeCarousels)
