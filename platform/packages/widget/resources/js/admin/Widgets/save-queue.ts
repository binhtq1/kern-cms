type QueueCallbacks<T> = {
    save: (snapshot: T, signal: AbortSignal) => Promise<void>;
    onSaved: (snapshot: T) => void;
    onError: (error: unknown) => void;
    onState: (saving: boolean) => void;
};

/** Serialize writes and retain only the newest waiting layout, without retrying failed writes indefinitely. */
export function createSaveQueue<T>(callbacks: QueueCallbacks<T>) {
    let pending: T | null = null;
    let running = false;
    let blocked = false;
    let disposed = false;
    let controller: AbortController | null = null;
    async function drain() {
        if (running || blocked || disposed || pending === null) return;
        running = true;
        callbacks.onState(true);
        while (pending !== null && !blocked && !disposed) {
            const snapshot: T = pending;
            pending = null;
            controller = new AbortController();
            try {
                await callbacks.save(snapshot, controller.signal);
                if (disposed) break;
                callbacks.onSaved(snapshot);
            } catch (error) {
                if (disposed) break;
                pending ??= snapshot;
                blocked = true;
                callbacks.onError(error);
            }
        }
        controller = null;
        running = false;
        if (!disposed) callbacks.onState(false);
    }
    return {
        enqueue(snapshot: T) {
            if (disposed) return;
            pending = structuredClone(snapshot);
            blocked = false;
            void drain();
        },
        retry() {
            blocked = false;
            void drain();
        },
        cancel() {
            disposed = true;
            pending = null;
            controller?.abort();
        },
    };
}

export function committedLayout<
    Config,
    Group extends { items: { key: string; data: Config }[] },
>(groups: Group[], committed: ReadonlyMap<string, Config>): Group[] {
    return groups.map((area) => ({
        ...area,
        items: area.items.map((item) => ({
            ...item,
            data: committed.get(item.key) ?? item.data,
        })),
    })) as Group[];
}
