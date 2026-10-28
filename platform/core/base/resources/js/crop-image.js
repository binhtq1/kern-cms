const controllers = new WeakMap()
class CropImage {
    constructor(modal) {
        this.modal = modal
        this.image = modal.querySelector('.cropper-image')
        this.cropper = null
        this.url = null
        modal.addEventListener('change', (event) => {
            if (!event.target.matches('input[type="file"]') || !event.target.files?.[0]) return
            if (this.url) URL.revokeObjectURL(this.url)
            this.url = URL.createObjectURL(event.target.files[0])
            this.load(this.url)
        })
        modal.addEventListener('shown.bs.modal', (event) => {
            const source = event.relatedTarget?.closest('.crop-image-container')?.querySelector('.crop-image-original')?.src
            if (source) this.load(source)
        })
        modal.addEventListener('hidden.bs.modal', () => this.destroy())
        modal.addEventListener('click', (event) => {
            const button = event.target.closest('button[type="submit"]')
            if (!button) return
            event.preventDefault()
            this.save(button)
        })
    }
    load(source) {
        if (!this.image) return
        this.cropper?.destroy()
        this.cropper = null
        this.image.onload = () => {
            if (window.Cropper) this.cropper = new Cropper(this.image, { aspectRatio: 1, preview: '.img-preview' })
        }
        this.image.src = source
    }
    async save(button) {
        const form = this.modal.querySelector('form')
        const file = form?.querySelector('input[type="file"]')
        if (!this.cropper || !file || button.disabled) return
        const canvas = this.cropper.getCroppedCanvas({ width: 160, height: 160 })
        button.disabled = true
        try {
            const blob = await new Promise((resolve) => canvas.toBlob(resolve))
            if (!blob) throw new Error('Could not crop this image.')
            const data = new FormData(form)
            data.set(file.name, blob, 'avatar.png')
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } })
            const result = await response.json()
            if (!response.ok || result.error) throw new Error(result.message || response.statusText)
            this.updateImage(result.data.url)
            window.Kern?.showSuccess(result.message)
            window.bootstrap?.Modal.getInstance(this.modal)?.hide()
        } catch (error) { window.Kern?.showError(error.message) }
        finally { button.disabled = false }
    }
    updateImage(url) {
        document.querySelectorAll('.crop-image-original').forEach((element) => {
            if (element instanceof HTMLImageElement) element.src = url
            else element.style.backgroundImage = `url(${JSON.stringify(url)})`
        })
    }
    destroy() {
        this.cropper?.destroy()
        this.cropper = null
        if (this.url) URL.revokeObjectURL(this.url)
        this.url = null
        if (this.image) { this.image.onload = null; this.image.removeAttribute('src') }
        this.modal.querySelectorAll('input[type="file"]').forEach((input) => { input.value = '' })
    }
}
function initialize() {
    document.querySelectorAll('.crop-image-modal').forEach((modal) => {
        if (!controllers.has(modal)) controllers.set(modal, new CropImage(modal))
    })
}
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-bb-toggle="delete-avatar"]')
    if (!button) return
    event.preventDefault()
    try {
        const response = await fetch(button.href, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } })
        const result = await response.json()
        if (!response.ok || result.error) throw new Error(result.message || response.statusText)
        document.querySelectorAll('.crop-image-original').forEach((element) => {
            if (element instanceof HTMLImageElement) element.src = result.data.url
            else element.style.backgroundImage = `url(${JSON.stringify(result.data.url)})`
        })
        window.Kern?.showSuccess(result.message)
    } catch (error) { window.Kern?.showError(error.message) }
})
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
else initialize()
document.addEventListener('core-init-resources', initialize)
