export function createUploadSession() {
    let current: AbortController | null = null;
    return {
        begin() {
            current?.abort();
            current = new AbortController();
            return current;
        },
        active(controller: AbortController) {
            return current === controller && !controller.signal.aborted;
        },
        finish(controller: AbortController) {
            if (current !== controller) return false;
            current = null;
            return true;
        },
        cancel() {
            current?.abort();
            current = null;
        },
    };
}
