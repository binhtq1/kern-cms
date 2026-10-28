import type { router } from "@inertiajs/react";

export function createTableNavigation(
    visit: typeof router.get,
    onLoading: (loading: boolean) => void,
) {
    let active: object | null = null;
    let cancelVisit: (() => void) | null = null;
    return {
        cancel() {
            active = null;
            cancelVisit?.();
            cancelVisit = null;
        },
        get(...args: Parameters<typeof router.get>) {
            if (active) return;
            const request = {};
            active = request;
            let started = false;
            const [url, data, options] = args;
            const finish = () => {
                if (active !== request) return;
                active = null;
                cancelVisit = null;
                onLoading(false);
            };
            try {
                visit(url, data, {
                    ...options,
                    onStart: (visit) => {
                        if (active !== request) return;
                        started = true;
                        onLoading(true);
                        options?.onStart?.(visit);
                    },
                    onCancelToken: (token) => {
                        if (active !== request) {
                            token.cancel();
                            return;
                        }
                        cancelVisit = () => token.cancel();
                        options?.onCancelToken?.(token);
                    },
                    onFinish: (visit) => {
                        if (active !== request) return;
                        finish();
                        options?.onFinish?.(visit);
                    },
                });
                // A canceled before handler returns without onFinish.
                if (!started) finish();
            } catch (error) {
                finish();
                throw error;
            }
        },
    };
}
