export type MediaUploadLifecycle = {
    isOwner: () => boolean;
    isCurrent: () => boolean;
    isCancelled: (reason: unknown) => boolean;
    complete: () => void;
    fail: (reason: unknown) => void;
    release: () => void;
    refresh: () => void;
};

export async function runMediaUpload(
    operation: () => Promise<void>,
    lifecycle: MediaUploadLifecycle,
): Promise<void> {
    try {
        await operation();
        if (lifecycle.isCurrent()) lifecycle.complete();
    } catch (reason) {
        if (lifecycle.isCurrent() && !lifecycle.isCancelled(reason))
            lifecycle.fail(reason);
    } finally {
        if (lifecycle.isOwner()) {
            const refresh = lifecycle.isCurrent();
            lifecycle.release();
            if (refresh) lifecycle.refresh();
        }
    }
}
