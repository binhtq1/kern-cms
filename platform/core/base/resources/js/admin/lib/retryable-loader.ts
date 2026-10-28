export function createRetryableLoader<T>(load: () => Promise<T>) {
    let pending: Promise<T> | undefined;
    return {
        load: () => (pending ??= Promise.resolve().then(load)),
        reset: () => {
            pending = undefined;
        },
    };
}
