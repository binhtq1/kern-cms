const initialized = new WeakSet()
function initialize() {
    if (!window.intlTelInput) return
    document.querySelectorAll('.js-phone-number-mask').forEach((element) => {
        if (initialized.has(element)) return
        initialized.add(element)
        window.intlTelInput(element, {
            initialCountry: element.dataset.country || 'auto',
            geoIpLookup: async (callback) => {
                try {
                    const response = await fetch('https://ipinfo.io/json', { credentials: 'omit' })
                    const data = await response.json()
                    callback(data.country || 'us')
                } catch { callback('us') }
            },
            utilsScript: '/vendor/core/core/base/libraries/intl-tel-input/js/utils.js',
        })
    })
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
document.addEventListener('payment-form-reloaded', initialize)
document.addEventListener('core-init-resources', initialize)
