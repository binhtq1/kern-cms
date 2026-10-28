export type UploadProgressItem = {
    id: string;
    name: string;
    size: number;
    percent: number;
    status: "queued" | "uploading" | "uploaded" | "error" | "cancelled";
    error?: string;
};

export function createUploadProgress(
    onChange: (items: UploadProgressItem[]) => void,
) {
    let generation = 0;
    let items: UploadProgressItem[] = [];
    function handle(batch: number) {
        return {
            update(
                index: number,
                patch: Partial<
                    Pick<UploadProgressItem, "status" | "percent" | "error">
                >,
            ) {
                if (
                    batch !== generation ||
                    !items[index] ||
                    !["queued", "uploading"].includes(items[index].status)
                )
                    return;
                const percent = patch.percent ?? items[index].percent;
                items = items.map((item, position) =>
                    position === index
                        ? {
                              ...item,
                              ...patch,
                              percent:
                                  patch.status === "uploaded"
                                      ? 100
                                      : Math.max(
                                            item.percent,
                                            Math.min(
                                                99,
                                                Math.max(
                                                    0,
                                                    Number.isFinite(percent)
                                                        ? percent
                                                        : item.percent,
                                                ),
                                            ),
                                        ),
                          }
                        : item,
                );
                onChange(items);
            },
            cancel() {
                if (batch !== generation) return;
                items = items.map((item) =>
                    ["queued", "uploading"].includes(item.status)
                        ? { ...item, status: "cancelled" }
                        : item,
                );
                onChange(items);
            },
        };
    }
    return {
        clear() {
            generation++;
            items = [];
            onChange(items);
        },
        dispose() {
            generation++;
        },
        retry(id: string) {
            if (
                items.some((item) =>
                    ["queued", "uploading"].includes(item.status),
                )
            )
                return null;
            const index = items.findIndex(
                (item) => item.id === id && item.status === "error",
            );
            if (index < 0) return null;
            const batch = ++generation;
            items = items.map((item, position) =>
                position === index
                    ? {
                          ...item,
                          status: "uploading",
                          percent: 0,
                          error: undefined,
                      }
                    : item,
            );
            onChange(items);
            return { index, ...handle(batch) };
        },
        start(files: { name: string; size: number }[]) {
            const batch = ++generation;
            items = files.map((file, index) => ({
                id: `${batch}:${index}`,
                name: file.name,
                size: file.size,
                percent: 0,
                status: "queued",
            }));
            onChange(items);
            return handle(batch);
        },
    };
}

export function formatUploadSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    const unit = Math.min(3, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / 1024 ** unit).toFixed(1)} ${["B", "KB", "MB", "GB"][unit]}`;
}

export function chunkUploadPercent(
    fileSize: number,
    chunkSize: number,
    chunk: number,
    event: { loaded?: number; total?: number; progress?: number },
) {
    const chunkBytes = Math.max(
        0,
        Math.min(chunkSize, fileSize - chunk * chunkSize),
    );
    const fraction =
        event.progress ??
        (event.total ? (event.loaded ?? 0) / event.total : undefined);
    const bytes =
        fraction === undefined
            ? Math.min(chunkBytes, event.loaded ?? 0)
            : Math.max(0, Math.min(1, fraction)) * chunkBytes;
    return fileSize > 0
        ? Math.max(
              0,
              Math.min(
                  99,
                  Math.floor(((chunk * chunkSize + bytes) / fileSize) * 100),
              ),
          )
        : 0;
}
