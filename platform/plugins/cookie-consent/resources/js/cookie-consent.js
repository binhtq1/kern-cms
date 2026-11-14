function initializeCookieConsent() {
    const settings = document.querySelector('div[data-site-cookie-name]')
    if (!settings) return
    const {
        siteCookieName: name,
        siteCookieDomain: domain,
        siteCookieLifetime: lifetime,
        siteSessionSecure: secure,
    } = settings.dataset
    const dialogs = [...document.querySelectorAll('.js-cookie-consent')]
    const hideCookieDialog = () =>
        dialogs.forEach((dialog) => {
            dialog.hidden = true
            dialog.style.display = 'none'
        })
    const consentWithCookies = () => {
        const expires = new Date(Date.now() + Number(lifetime) * 86400000).toUTCString()
        document.cookie =
            encodeURIComponent(name) +
            '=1;expires=' +
            expires +
            (domain ? ';domain=' + domain : '') +
            ';path=/' +
            (secure || '') +
            ';SameSite=Lax'
        hideCookieDialog()
    }
    dialogs.forEach((dialog) => {
        dialog.classList.add('cookie-consent--visible')
        dialog.setAttribute('aria-label', dialog.getAttribute('aria-label') || 'Cookie consent')
    })
    if (document.cookie.split(';').some((cookie) => cookie.trim() === encodeURIComponent(name) + '=1'))
        hideCookieDialog()
    document.addEventListener('click', (event) => {
        if (event.target.closest('.js-cookie-consent-agree')) {
            event.preventDefault()
            consentWithCookies()
        }
    })
    window.kernCookieConsent = { consentWithCookies, hideCookieDialog }
}

if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', initializeCookieConsent, { once: true })
else initializeCookieConsent()
