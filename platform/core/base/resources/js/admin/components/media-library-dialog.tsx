import { isMediaPicker, requirePayload } from "@admin/lib/admin-api";
import { currentCsrfHeaders } from "@admin/lib/admin-session";
import { useAdminTranslation } from "@admin/lib/translate";
import { createUploadSession } from "@admin/lib/upload-session";
import { cn } from "@admin/lib/utils";
import type { SharedProps } from "@admin/types";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import {
    Check,
    ChevronRight,
    Folder,
    ImageIcon,
    Upload,
    X,
    ZoomIn,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { FileUpload } from "./file-upload";
import { LoadingIndicator } from "./loading-indicator";
import { UploadProgressPanel } from "./upload-progress-panel";
import { useUploadProgress } from "@admin/hooks/use-upload-progress";
import { SearchInput } from "./search-input";
import { notify } from "./toast";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";

export type MediaFile = {
    id: string;
    name: string;
    url: string;
    src: string;
    mime_type?: string;
};
type FolderItem = { id: string; name: string };
type Result = {
    data: MediaFile[];
    folders: FolderItem[];
    breadcrumbs: FolderItem[];
    current_page: number;
    last_page: number;
};
type MediaResponse = Omit<Result, "folders" | "breadcrumbs"> &
    Partial<Pick<Result, "folders" | "breadcrumbs">>;
type UploadSource = { files: File[]; folder: string; uploadUrl: string };
export function MediaLibraryDialog({
    open,
    onOpenChange,
    listUrl,
    uploadUrl: requestedUploadUrl,
    imagesOnly = true,
    multiple = false,
    onInsert,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    listUrl: string;
    uploadUrl?: string;
    imagesOnly?: boolean;
    multiple?: boolean;
    onInsert: (files: MediaFile[]) => void;
}) {
    const t = useAdminTranslation();
    const { admin } = usePage<SharedProps>().props;
    const canBrowseMedia = admin.capabilities?.browseMedia === true;
    const uploadUrl = admin.capabilities?.uploadMedia
        ? requestedUploadUrl
        : undefined;
    const [folder, setFolder] = useState("0");
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<Result>({
        data: [],
        folders: [],
        breadcrumbs: [],
        current_page: 1,
        last_page: 1,
    });
    const [selected, setSelected] = useState<MediaFile[]>([]);
    const [preview, setPreview] = useState<MediaFile | null>(null);
    const [zoom, setZoom] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [version, setVersion] = useState(0);
    const [drop, setDrop] = useState(false);
    const [uploading, setUploading] = useState(false);
    const {
        items: uploadItems,
        start: beginProgress,
        retry: retryProgress,
        clear: clearProgress,
    } = useUploadProgress();
    const sourceRef = useRef<UploadSource | null>(null);
    const dismissUpload = useCallback(() => {
        sourceRef.current = null;
        clearProgress();
    }, [clearProgress]);
    const uploadController = useRef<AbortController | null>(null);
    const uploadSession = useRef(createUploadSession());
    const anchor = useRef<string | null>(null);
    useEffect(() => {
        if (open) {
            setSelected([]);
            setPreview(null);
            setZoom(false);
            setError("");
            anchor.current = null;
        } else {
            uploadSession.current.cancel();
            uploadController.current = null;
            setUploading(false);
            dismissUpload();
            setDrop(false);
            setZoom(false);
        }
    }, [open, dismissUpload]);
    useEffect(() => () => uploadSession.current.cancel(), []);
    useEffect(() => {
        if (!open) {
            uploadController.current?.abort();
            return;
        }
        if (!canBrowseMedia) {
            setResult({
                data: [],
                folders: [],
                breadcrumbs: [],
                current_page: 1,
                last_page: 1,
            });
            setError(t("You do not have permission to browse media."));
            setLoading(false);
            return;
        }
        const controller = new AbortController();
        setLoading(true);
        setError("");
        const timer = setTimeout(() => {
            axios
                .get<MediaResponse>(listUrl, {
                    signal: controller.signal,
                    params: {
                        folder_id: folder,
                        search,
                        page,
                        ...(imagesOnly ? { type: "image" } : {}),
                    },
                })
                .then(({ data }) => {
                    if (controller.signal.aborted) return;
                    data = requirePayload<MediaResponse>(data, isMediaPicker);
                    if (!controller.signal.aborted)
                        setResult({
                            ...data,
                            folders: data.folders ?? [],
                            breadcrumbs: data.breadcrumbs ?? [],
                        });
                })
                .catch((reason) => {
                    if (!controller.signal.aborted)
                        setError(
                            reason.response?.data?.message ||
                                t(
                                    "Unable to load media. Check your session and permissions.",
                                ),
                        );
                })
                .finally(() => {
                    if (!controller.signal.aborted) setLoading(false);
                });
        }, 180);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        open,
        listUrl,
        folder,
        search,
        page,
        version,
        imagesOnly,
        canBrowseMedia,
        t,
    ]);
    function navigate(id: string) {
        setFolder(id);
        setPage(1);
        setSearch("");
        anchor.current = null;
    }
    function choose(file: MediaFile, range: boolean) {
        setPreview(file);
        if (!multiple) {
            setSelected([file]);
            return;
        }
        const from = result.data.findIndex(
            (entry) => entry.id === anchor.current,
        );
        const to = result.data.findIndex((entry) => entry.id === file.id);
        if (range && from >= 0) {
            const files = result.data.slice(
                Math.min(from, to),
                Math.max(from, to) + 1,
            );
            setSelected((items) => [
                ...items.filter(
                    (entry) => !files.some((file) => file.id === entry.id),
                ),
                ...files,
            ]);
        } else {
            setSelected((items) =>
                items.some((entry) => entry.id === file.id)
                    ? items.filter((entry) => entry.id !== file.id)
                    : [...items, file],
            );
            anchor.current = file.id;
        }
    }
    async function upload(files: FileList | File[]) {
        if (!uploadUrl || uploadController.current || !files.length) return;
        const queue = Array.from(files);
        if (
            imagesOnly &&
            queue.some((file) => !file.type.startsWith("image/"))
        ) {
            setError(t("Choose an image file"));
            return;
        }
        const source = { files: queue, folder, uploadUrl };
        sourceRef.current = source;
        await runUpload(
            source,
            beginProgress(queue),
            queue.map((_, index) => index),
        );
    }
    async function retryUpload(id: string) {
        const source = sourceRef.current;
        if (!source || uploadController.current) return;
        const batch = retryProgress(id);
        if (batch) await runUpload(source, batch, [batch.index]);
    }
    async function runUpload(
        source: UploadSource,
        batch: ReturnType<typeof beginProgress>,
        indices: number[],
    ) {
        const controller = uploadSession.current.begin();
        uploadController.current = controller;
        const { files: queue, folder, uploadUrl } = source;
        setUploading(true);
        setError("");
        let count = 0;
        const failed: string[] = [];
        for (const index of indices) {
            const file = queue[index];
            if (controller.signal.aborted) break;
            batch.update(index, { status: "uploading" });
            const body = new FormData();
            body.append("file", file);
            body.append("folder_id", folder);
            try {
                const { data } = await axios.post(uploadUrl, body, {
                    signal: controller.signal,
                    headers: {
                        Accept: "application/json",
                        ...currentCsrfHeaders(undefined, undefined, uploadUrl),
                    },
                    onUploadProgress: (event) =>
                        uploadSession.current.active(controller) &&
                        batch.update(index, {
                            percent: event.total
                                ? Math.round((event.loaded / event.total) * 100)
                                : 0,
                        }),
                });
                if (!uploadSession.current.active(controller)) break;
                if (data.error)
                    throw new Error(data.message || t("Upload failed."));
                batch.update(index, { status: "uploaded" });
                const uploaded = data.data as MediaFile;
                setSelected((items) =>
                    multiple ? [...items, uploaded] : [uploaded],
                );
                setPreview(uploaded);
                count++;
            } catch (reason) {
                if (controller.signal.aborted) break;
                const message =
                    (axios.isAxiosError(reason)
                        ? reason.response?.data?.message || reason.message
                        : reason instanceof Error
                          ? reason.message
                          : undefined) || t("Upload failed.");
                failed.push(`${file.name}: ${message}`);
                batch.update(index, {
                    status: "error",
                    error: message,
                });
            }
        }
        if (!uploadSession.current.finish(controller)) return;
        batch.cancel();
        setUploading(false);
        uploadController.current = null;
        setVersion((value) => value + 1);
        if (count) {
            notify(t(":count files uploaded", { count }));
        }
        if (failed.length) {
            setError(failed.join("\n"));
            notify(t("Upload failed."), true);
        }
    }
    const allSelected =
        result.data.length > 0 &&
        result.data.every((file) =>
            selected.some((entry) => entry.id === file.id),
        );
    return (
        <Dialog
            size="xl"
            open={open}
            onOpenChange={onOpenChange}
            title={t("Media library")}
            footer={
                <div className="flex w-full flex-wrap items-center justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2 text-sm text-muted-foreground">
                        <span>
                            {selected.length} {t("selected")}
                        </span>
                        {multiple && selected.length > 0 && (
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-8 shrink-0"
                                aria-label={t("Clear selection")}
                                title={t("Clear selection")}
                                disabled={!!uploading}
                                onClick={() => setSelected([])}
                            >
                                <X className="size-4" />
                            </Button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            disabled={!selected.length || !!uploading}
                            onClick={() => {
                                onInsert(selected);
                                onOpenChange(false);
                            }}
                        >
                            {t("Insert")}
                            {multiple && selected.length > 0
                                ? ` (${selected.length})`
                                : ""}
                        </Button>
                    </div>
                </div>
            }
        >
            <div className="flex flex-wrap items-center gap-2 border-b pb-3">
                <SearchInput
                    disabled={!!uploading}
                    className="min-w-48 flex-1"
                    aria-label={t("Search media")}
                    placeholder={t("Search media…")}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setPage(1);
                    }}
                    onClear={() => {
                        setSearch("");
                        setPage(1);
                    }}
                />
                {uploadUrl && (
                    <FileUpload
                        multiple
                        accept={imagesOnly ? "image/*" : undefined}
                        disabled={!!uploading}
                        onFiles={(files) => void upload(files)}
                    />
                )}
            </div>
            <nav
                aria-label={t("Folders")}
                className="flex flex-wrap items-center gap-1 py-3 text-sm"
            >
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={folder === "0" || !!uploading}
                    onClick={() => navigate("0")}
                >
                    <Folder className="size-4" />
                    {t("All media")}
                </Button>
                {result.breadcrumbs.map((entry) => (
                    <span key={entry.id} className="flex items-center gap-1">
                        <ChevronRight className="size-3 text-muted-foreground" />
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={entry.id === folder || !!uploading}
                            onClick={() => navigate(entry.id)}
                        >
                            {entry.name}
                        </Button>
                    </span>
                ))}
            </nav>
            <UploadProgressPanel
                key={uploadItems[0]?.id}
                inline
                items={uploadItems}
                onCancel={() => uploadController.current?.abort()}
                onDismiss={dismissUpload}
                onRetry={(id) => void retryUpload(id)}
            />
            {error && (
                <p
                    role="alert"
                    className="mb-3 whitespace-pre-line text-sm text-destructive"
                >
                    {error}
                </p>
            )}
            <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_200px]">
                <div
                    onDragOver={(event) => {
                        if (
                            uploadUrl &&
                            event.dataTransfer.types.includes("Files")
                        ) {
                            event.preventDefault();
                            setDrop(true);
                        }
                    }}
                    onDragLeave={(event) => {
                        if (
                            !(event.relatedTarget instanceof Node) ||
                            !event.currentTarget.contains(event.relatedTarget)
                        )
                            setDrop(false);
                    }}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDrop(false);
                        if (uploadUrl) void upload(event.dataTransfer.files);
                    }}
                    className={cn(
                        "relative min-h-72 rounded-md border bg-muted/10 p-3",
                        drop && "border-primary ring-2 ring-primary/30",
                    )}
                >
                    {drop && (
                        <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-md bg-card/95 text-sm font-medium text-primary-ink">
                            <Upload className="mr-2 size-5" />
                            {t("Drop files to upload")}
                        </div>
                    )}
                    {uploadUrl && (
                        <p className="mb-3 text-xs text-muted-foreground">
                            {t("Drop files to upload")}
                        </p>
                    )}
                    {multiple && (
                        <div className="mb-3 flex items-center justify-between gap-2 text-xs">
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={
                                    loading ||
                                    !!uploading ||
                                    !result.data.length
                                }
                                onClick={() =>
                                    setSelected((items) =>
                                        allSelected
                                            ? items.filter(
                                                  (entry) =>
                                                      !result.data.some(
                                                          (file) =>
                                                              file.id ===
                                                              entry.id,
                                                      ),
                                              )
                                            : [
                                                  ...items.filter(
                                                      (entry) =>
                                                          !result.data.some(
                                                              (file) =>
                                                                  file.id ===
                                                                  entry.id,
                                                          ),
                                                  ),
                                                  ...result.data,
                                              ],
                                    )
                                }
                            >
                                {t(allSelected ? "Deselect all" : "Select all")}
                            </Button>
                            <span className="text-muted-foreground">
                                {t("Shift-click to select a range")}
                            </span>
                        </div>
                    )}
                    <div
                        aria-busy={loading}
                        className={cn(
                            "grid max-h-[40vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 md:max-h-[48vh]",
                            loading && "pointer-events-none opacity-50",
                        )}
                    >
                        {!loading &&
                            result.folders.map((entry) => (
                                <button
                                    type="button"
                                    disabled={!!uploading}
                                    key={`folder-${entry.id}`}
                                    onClick={() => navigate(entry.id)}
                                    className="flex min-h-28 flex-col items-center justify-center gap-2 rounded-md border bg-card p-3 outline-none hover:border-primary focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <Folder
                                        className="size-10 fill-primary/10 text-primary-ink"
                                        strokeWidth={1.25}
                                    />
                                    <span className="max-w-full truncate text-xs">
                                        {entry.name}
                                    </span>
                                </button>
                            ))}
                        {result.data.map((file) => {
                            const picked = selected.some(
                                (entry) => entry.id === file.id,
                            );
                            return (
                                <button
                                    type="button"
                                    key={file.id}
                                    disabled={loading || !!uploading}
                                    aria-pressed={picked}
                                    title={file.name}
                                    onClick={(event) =>
                                        choose(file, event.shiftKey)
                                    }
                                    onDoubleClick={() => {
                                        setPreview(file);
                                        setSelected((items) =>
                                            multiple
                                                ? [
                                                      ...items.filter(
                                                          (entry) =>
                                                              entry.id !==
                                                              file.id,
                                                      ),
                                                      file,
                                                  ]
                                                : [file],
                                        );
                                        setZoom(true);
                                    }}
                                    className={cn(
                                        "relative overflow-hidden rounded-md border bg-card text-left outline-none hover:border-primary focus-visible:ring-2 focus-visible:ring-ring",
                                        picked &&
                                            "border-primary ring-2 ring-primary/30",
                                    )}
                                >
                                    {file.mime_type?.startsWith("image/") ? (
                                        <img
                                            src={file.src}
                                            alt=""
                                            loading="lazy"
                                            className="aspect-square w-full bg-muted/30 object-contain"
                                        />
                                    ) : (
                                        <div className="flex aspect-square items-center justify-center bg-muted/30">
                                            <ImageIcon className="size-8 text-muted-foreground" />
                                        </div>
                                    )}
                                    {picked && (
                                        <span className="absolute top-2 right-2 rounded-full bg-primary p-1 text-primary-foreground">
                                            <Check className="size-3" />
                                        </span>
                                    )}
                                    <span className="block truncate p-2 text-xs">
                                        {file.name}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {loading && (
                        <LoadingIndicator
                            label={t("Loading media…")}
                            className="pointer-events-none absolute inset-0 flex rounded-md bg-card/70"
                            iconClassName="size-5"
                        />
                    )}
                    {!loading &&
                        !result.data.length &&
                        !result.folders.length && (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                {t("No media found.")}
                            </p>
                        )}
                </div>
                {preview && (
                    <Button
                        variant="outline"
                        className="justify-start md:hidden"
                        onClick={() => setZoom(true)}
                    >
                        <ZoomIn className="size-4" />
                        <span className="truncate">
                            {t("Preview")}: {preview.name}
                        </span>
                    </Button>
                )}
                <aside className="hidden min-w-0 rounded-md border bg-muted/10 p-3 md:block">
                    {preview ? (
                        <>
                            <button
                                type="button"
                                className="group relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-md bg-muted/30"
                                aria-label={t("Preview")}
                                onClick={() => setZoom(true)}
                            >
                                {preview.mime_type?.startsWith("image/") ? (
                                    <img
                                        src={preview.src}
                                        alt={preview.name}
                                        className="size-full object-contain"
                                    />
                                ) : (
                                    <ImageIcon className="size-12 text-muted-foreground" />
                                )}
                                <span className="absolute right-2 bottom-2 rounded border bg-card p-1.5">
                                    <ZoomIn className="size-4" />
                                </span>
                            </button>
                            <p className="mt-3 break-words text-sm font-medium">
                                {preview.name}
                            </p>
                            <p className="mt-1 break-all text-xs text-muted-foreground">
                                {preview.mime_type}
                            </p>
                        </>
                    ) : (
                        <div className="flex h-full min-h-32 flex-col items-center justify-center gap-3 text-center text-xs text-muted-foreground">
                            <ImageIcon className="size-10" strokeWidth={1} />
                            {t("Choose a file from the library")}
                        </div>
                    )}
                </aside>
            </div>
            <div className="mt-3 flex items-center justify-between gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={loading || page <= 1}
                    onClick={() => setPage(page - 1)}
                >
                    {t("Previous")}
                </Button>
                <span className="text-xs tabular-nums">
                    {t("Page")} {result.current_page} {t("of")}{" "}
                    {result.last_page}
                </span>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={loading || page >= result.last_page}
                    onClick={() => setPage(page + 1)}
                >
                    {t("Next")}
                </Button>
            </div>
            <Dialog
                size="xl"
                open={zoom && !!preview}
                onOpenChange={setZoom}
                title={preview?.name ?? t("Preview")}
            >
                {preview?.mime_type?.startsWith("image/") ? (
                    <img
                        src={preview.src}
                        alt={preview.name}
                        className="max-h-[70vh] w-full object-contain"
                    />
                ) : (
                    <p className="break-all text-sm">{preview?.name}</p>
                )}
            </Dialog>
        </Dialog>
    );
}
