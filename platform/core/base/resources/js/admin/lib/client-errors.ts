export type ClientErrorSource =
    "boundary" | "window" | "rejection" | "editor" | "analytics";
export type ClientErrorRecord = {
    source: ClientErrorSource;
    name: string;
    count: number;
};
const safeNames = new Set([
    "Error",
    "TypeError",
    "RangeError",
    "ReferenceError",
    "SyntaxError",
    "ChunkLoadError",
]);
const safeSources = new Set([
    "boundary",
    "window",
    "rejection",
    "editor",
    "analytics",
]);

export function createClientErrorReporter(
    sink: (record: ClientErrorRecord) => void = () => {},
    limit = 20,
) {
    const records = new Map<string, ClientErrorRecord>();
    const capacity = Math.max(
        1,
        Math.min(100, Number.isFinite(limit) ? Math.floor(limit) : 20),
    );
    return {
        report(error: unknown, source: ClientErrorSource = "boundary") {
            if (!safeSources.has(source)) source = "boundary";
            let name = "Error";
            try {
                if (error instanceof Error) {
                    const candidate = error.name;
                    if (safeNames.has(candidate)) name = candidate;
                }
            } catch {
                /* Thrown values may have unsafe accessors. */
            }
            const key = `${source}:${name}`;
            const existing = records.get(key);
            if (existing) {
                existing.count = Math.min(9999, existing.count + 1);
                return;
            }
            if (records.size >= capacity)
                records.delete(records.keys().next().value!);
            const record = { source, name, count: 1 };
            records.set(key, record);
            try {
                sink({ ...record });
            } catch {
                /* Reporting must not crash the app. */
            }
        },
        records: () => [...records.values()].map((record) => ({ ...record })),
    };
}

export const clientErrorReporter = createClientErrorReporter((record) =>
    console.warn("[Admin] Client error", record),
);
const installations = new WeakMap<EventTarget, () => void>();
export function installClientErrorReporting(
    target: EventTarget = window,
    reporter = clientErrorReporter,
): () => void {
    const existing = installations.get(target);
    if (existing) return existing;
    const error = (event: Event) =>
        reporter.report((event as ErrorEvent).error, "window");
    const rejection = (event: Event) =>
        reporter.report((event as PromiseRejectionEvent).reason, "rejection");
    target.addEventListener("error", error);
    target.addEventListener("unhandledrejection", rejection);
    const uninstall = () => {
        if (installations.get(target) !== uninstall) return;
        target.removeEventListener("error", error);
        target.removeEventListener("unhandledrejection", rejection);
        installations.delete(target);
    };
    installations.set(target, uninstall);
    return uninstall;
}
