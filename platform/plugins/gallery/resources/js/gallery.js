function initializeGallery() {
    const container = document.getElementById('list-photo')
    if (!container || container.dataset.nativeGallery) return
    container.dataset.nativeGallery = 'true'
    const items = [...container.querySelectorAll('[data-src]')]
    if (!items.length) return
    Object.assign(container.style, {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 220px), 1fr))',
        gap: '16px',
    })
    const dialog = document.createElement('dialog')
    dialog.className = 'kern-gallery-dialog'
    dialog.setAttribute('aria-label', container.dataset.galleryLabel || 'Photo gallery')
    Object.assign(dialog.style, {
        width: 'min(94vw, 1200px)',
        maxHeight: '95vh',
        padding: '16px',
        border: '0',
        borderRadius: '8px',
        background: '#111',
        color: '#fff',
    })
    const stage = document.createElement('div')
    Object.assign(stage.style, { display: 'grid', gap: '12px' })
    const controls = document.createElement('div')
    Object.assign(controls.style, { display: 'flex', justifyContent: 'space-between', gap: '12px' })
    const button = (text, label, action) => {
        const element = document.createElement('button')
        element.type = 'button'
        element.textContent = text
        element.setAttribute('aria-label', label)
        Object.assign(element.style, {
            minWidth: '44px',
            minHeight: '44px',
            border: '1px solid currentColor',
            borderRadius: '4px',
            background: 'transparent',
            color: 'inherit',
            cursor: 'pointer',
        })
        element.addEventListener('click', action)
        return element
    }
    let index = 0
    let opener
    const image = document.createElement('img')
    Object.assign(image.style, {
        display: 'block',
        maxWidth: '100%',
        maxHeight: '65vh',
        margin: 'auto',
        objectFit: 'contain',
    })
    const caption = document.createElement('p')
    caption.setAttribute('aria-live', 'polite')
    const thumbnails = document.createElement('div')
    Object.assign(thumbnails.style, { display: 'flex', overflowX: 'auto', gap: '8px' })
    const show = (next) => {
        index = (next + items.length) % items.length
        const item = items[index]
        image.src = item.dataset.src
        image.alt = item.querySelector('img')?.alt || ''
        const description = document.createElement('template')
        description.innerHTML = item.dataset.subHtml || ''
        caption.textContent = description.content.textContent
        thumbnails.querySelectorAll('button').forEach((thumbnail, position) => {
            thumbnail.setAttribute('aria-current', String(position === index))
            thumbnail.style.outline = position === index ? '2px solid #fff' : ''
        })
    }
    const close = () => dialog.close()
    controls.append(
        button('←', container.dataset.previousLabel || 'Previous photo', () => show(index - 1)),
        button('×', container.dataset.closeLabel || 'Close gallery', close),
        button('→', container.dataset.nextLabel || 'Next photo', () => show(index + 1)),
    )
    items.forEach((item, position) => {
        item.style.width = 'auto'
        item.style.position = 'static'
        item.tabIndex = 0
        item.setAttribute('role', 'button')
        item.setAttribute('aria-haspopup', 'dialog')
        item.setAttribute('aria-label', item.querySelector('img')?.alt || 'View photo ' + (position + 1))
        const open = (event) => {
            event.preventDefault()
            opener = item
            show(position)
            dialog.showModal()
        }
        item.addEventListener('click', open)
        item.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') open(event)
        })
        const thumbnail = button('', 'View photo ' + (position + 1), () => show(position))
        const small = document.createElement('img')
        small.src = item.querySelector('img')?.src || item.dataset.src
        small.alt = ''
        Object.assign(small.style, { width: '60px', height: '44px', objectFit: 'cover' })
        thumbnail.append(small)
        thumbnails.append(thumbnail)
    })
    stage.append(controls, image, caption, thumbnails)
    dialog.append(stage)
    document.body.append(dialog)
    dialog.addEventListener('close', () => opener?.focus())
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close()
    })
    dialog.addEventListener('keydown', (event) => {
        const rtl = getComputedStyle(container).direction === 'rtl'
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault()
            show(index + (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1))
        }
    })
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeGallery, { once: true })
else initializeGallery()
document.addEventListener('shortcode.loaded', initializeGallery)
