const pendingForms = new WeakSet()

document.addEventListener('submit', async (event) => {
    const form = event.target
    if (!(form instanceof HTMLFormElement) || !form.matches('.contact-form')) return
    event.preventDefault()
    event.stopPropagation()
    if (pendingForms.has(form)) return
    pendingForms.add(form)
    if (window.KernValidation && !(await window.KernValidation.validate(form))) {
        pendingForms.delete(form)
        return
    }
    if (!form.reportValidity()) {
        pendingForms.delete(form)
        return
    }

    const messages = form.parentElement
    const feedback = (selector, message = '') => {
        const element = form.querySelector(selector) ?? messages?.querySelector(selector)
        if (!element) return
        element.textContent = message
        element.hidden = !message
        element.style.display = message ? 'block' : 'none'
        element.setAttribute('role', selector.includes('error') ? 'alert' : 'status')
    }
    feedback('.contact-success-message')
    feedback('.contact-error-message')
    const buttons = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')]
    const states = buttons.map((button) => button.disabled)
    pendingForms.add(form)
    buttons.forEach((button) => {
        button.disabled = true
        button.classList.add('button-loading')
    })
    form.setAttribute('aria-busy', 'true')
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    form.querySelector('[name="_token"]')?.value ??
                    document.querySelector('meta[name="csrf-token"]')?.content ??
                    '',
            },
        })
        const data = await response.json()
        if (!response.ok || data.error) {
            const errors = data.errors ? Object.values(data.errors).flat().join('\n') : data.message
            if (data.errors) window.KernValidation?.showErrors(form, data.errors)
            feedback('.contact-error-message', errors || response.statusText)
            return
        }
        form.reset()
        feedback('.contact-success-message', data.message)
        document.dispatchEvent(new CustomEvent('contact-form.submitted', { detail: { form } }))
    } catch (error) {
        feedback('.contact-error-message', error.message || 'Unable to send your message. Please try again.')
    } finally {
        if (typeof window.refreshRecaptcha === 'function') window.refreshRecaptcha()
        buttons.forEach((button, index) => {
            button.disabled = states[index]
            button.classList.remove('button-loading')
        })
        form.removeAttribute('aria-busy')
        pendingForms.delete(form)
    }
})
