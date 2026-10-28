type RequestCallbacks<T> = {
    onSuccess: (value: T) => void;
    onError: (reason: unknown) => void;
    onSettled: () => void;
};

export function createLatestRequest() {
    let active: AbortController | null = null;

    function cancel() {
        active?.abort();
        active = null;
    }

    return {
        cancel,
        async run<T>(
            request: (signal: AbortSignal) => Promise<T>,
            callbacks: RequestCallbacks<T>,
        ): Promise<void> {
            cancel();
            const controller = new AbortController();
            active = controller;
            try {
                const value = await request(controller.signal);
                if (active === controller && !controller.signal.aborted) {
                    callbacks.onSuccess(value);
                }
            } catch (reason) {
                if (active === controller && !controller.signal.aborted) {
                    callbacks.onError(reason);
                }
            } finally {
                if (active === controller) {
                    active = null;
                    callbacks.onSettled();
                }
            }
        },
    };
}
