import { checkAdminFetchResponse } from "@admin/lib/admin-session";
import { currentCsrfHeaders } from "@admin/lib/admin-session";
import { FileUpload } from "./file-upload";
import { LoadingIndicator } from "./loading-indicator";
import { MediaLibraryDialog } from "./media-library-dialog";
import { notify } from "./toast";
import { useAdminTranslation } from "@admin/lib/translate";
import { useEffect, useRef, useState } from "react";
import { ImageIcon, X, FolderOpen } from "lucide-react";
import { useFieldControl } from "./field";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { createUploadSession } from "@admin/lib/upload-session";
import { cn } from "@admin/lib/utils";
import { usePage } from "@inertiajs/react";
import type { SharedProps } from "@admin/types";
import { mediaPreviewSource } from "./image-source";
type MediaFile = {
    id: string;
    name: string;
    url: string;
    src: string;
    mime_type?: string;
};
type MediaResult = {
    data: MediaFile[];
    current_page: number;
    last_page: number;
};
export function MediaPicker({
    id,
    value,
    onChange,
    listUrl: requestedListUrl,
    uploadUrl: requestedUploadUrl,
    imagesOnly = true,
    aspect = "landscape",
    compact = false,
    variant = "default",
    disabled = false,
}: {
    id?: string;
    value: string;
    onChange: (value: string, src?: string) => void;
    listUrl?: string;
    uploadUrl?: string;
    imagesOnly?: boolean;
    aspect?: "landscape" | "banner" | "square";
    compact?: boolean;
    variant?: "default" | "thumbnail" | "featured";
    disabled?: boolean;
}) {
    const t = useAdminTranslation();
    const { admin, memberMediaUrlPattern } = usePage<
        SharedProps & { memberMediaUrlPattern?: string }
    >().props;
    const listUrl =
        !disabled && admin.capabilities?.browseMedia
            ? requestedListUrl
            : undefined;
    const uploadUrl =
        !disabled && admin.capabilities?.uploadMedia
            ? requestedUploadUrl
            : undefined;
    const fieldControl = useFieldControl(id);
    const [open, setOpen] = useState(false);
    const uploadSession = useRef(createUploadSession());
    const currentValue = useRef(value);
    currentValue.current = value;
    useEffect(() => () => uploadSession.current.cancel(), []);
    const [preview, setPreview] = useState<{
        value: string;
        src: string;
    } | null>(null);
    const [imageFailed, setImageFailed] = useState(false);
    const [resolvedValue, setResolvedValue] = useState("");
    const directSource = mediaPreviewSource(value, memberMediaUrlPattern);
    const previewSource = preview?.value === value ? preview.src : directSource;
    const previewLoading = Boolean(
        value && listUrl && !previewSource && resolvedValue !== value,
    );
    useEffect(() => {
        setImageFailed(false);
        if (!value || !imagesOnly || !listUrl || directSource) return;
        const controller = new AbortController();
        const url = new URL(listUrl, window.location.origin);
        url.searchParams.set("path", value);
        url.searchParams.set("type", "image");
        fetch(url, {
            signal: controller.signal,
            headers: { Accept: "application/json" },
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((result: MediaResult | null) => {
                if (controller.signal.aborted) return;
                setResolvedValue(value);
                const file = result?.data.find((file) => file.url === value);
                if (file) {
                    setPreview({ value, src: file.src });
                    setImageFailed(false);
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) setResolvedValue(value);
            });
        return () => controller.abort();
    }, [value, imagesOnly, listUrl, directSource]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    async function upload(file?: File) {
        if (!file || !uploadUrl) {
            return;
        }
        if (imagesOnly && !file.type.startsWith("image/")) {
            setError(t("Choose an image file"));
            notify(t("Choose an image file"), "error");
            return;
        }
        const controller = uploadSession.current.begin();
        setLoading(true);
        setError("");
        const body = new FormData();
        body.append("file", file);
        try {
            const response = await fetch(uploadUrl, {
                method: "POST",
                signal: controller.signal,
                body,
                headers: {
                    Accept: "application/json",
                    ...currentCsrfHeaders(undefined, undefined, uploadUrl),
                },
            });
            await checkAdminFetchResponse(response);
            const data = await response.json();
            if (
                !uploadSession.current.active(controller) ||
                currentValue.current !== value
            )
                return;
            if (!response.ok || data.error) {
                throw new Error(data.message || t("Upload failed."));
            }
            const file = data.data;
            if (file?.url) {
                setImageFailed(false);
                setPreview({ value: file.url, src: file.src });
                onChange(file.url, file.src);
                setOpen(false);
            }
            notify(t("Upload complete"));
        } catch (reason) {
            if (!uploadSession.current.active(controller)) return;
            const message =
                reason instanceof Error ? reason.message : t("Upload failed.");
            setError(message);
            notify(message, "error");
        } finally {
            if (uploadSession.current.finish(controller)) setLoading(false);
        }
    }
    return (
        <div className="min-w-0 space-y-3">
            {imagesOnly && !compact && (
                <div
                    onDragOver={(event) => {
                        if (uploadUrl && !loading) event.preventDefault();
                    }}
                    onDrop={(event) => {
                        event.preventDefault();
                        if (uploadUrl && !loading)
                            void upload(event.dataTransfer.files[0]);
                    }}
                    className={cn(
                        "group relative flex w-full max-w-sm items-center justify-center overflow-hidden rounded-md border bg-muted/30",
                        aspect === "banner"
                            ? "aspect-[5/1] max-w-none"
                            : variant === "thumbnail"
                              ? "aspect-[4/3] max-w-none"
                              : variant === "featured"
                                ? "aspect-video max-h-36 max-w-none"
                                : aspect === "square"
                                  ? "aspect-square max-w-48"
                                  : "aspect-[4/3]",
                    )}
                >
                    {value && previewSource && !imageFailed ? (
                        <img
                            src={previewSource}
                            alt={t("Selected image")}
                            className="absolute inset-0 size-full object-contain"
                            onError={() => setImageFailed(true)}
                        />
                    ) : previewLoading ? (
                        <LoadingIndicator
                            label={t("Loading preview…")}
                            className="p-4"
                            iconClassName="size-5"
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-2 p-4 text-center text-muted-foreground">
                            <ImageIcon size={32} strokeWidth={1.25} />
                            <span className="text-xs">
                                {t(
                                    value
                                        ? "Image preview unavailable"
                                        : "No image selected",
                                )}
                            </span>
                        </div>
                    )}
                    {listUrl && (
                        <button
                            type="button"
                            className="absolute inset-0 cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                            aria-label={t(
                                value ? "Change image" : "Choose image",
                            )}
                            onClick={() => {
                                setOpen(true);
                            }}
                        />
                    )}
                    {value && !disabled && variant !== "thumbnail" && (
                        <Button
                            type="button"
                            size="icon"
                            variant="secondary"
                            className="absolute top-2 right-2 size-8 shadow-sm"
                            aria-label={t("Remove image")}
                            onClick={() => onChange("")}
                        >
                            <X size={14} />
                        </Button>
                    )}
                </div>
            )}
            <div className="flex flex-wrap gap-2">
                {listUrl && variant !== "thumbnail" && (
                    <Button
                        id={id}
                        {...fieldControl}
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setOpen(true);
                        }}
                    >
                        <FolderOpen size={15} />
                        {t(
                            imagesOnly
                                ? value
                                    ? "Change image"
                                    : "Choose image"
                                : "Browse media",
                        )}
                    </Button>
                )}
                {uploadUrl && variant !== "thumbnail" && (
                    <FileUpload
                        buttonSize="sm"
                        accept={imagesOnly ? "image/*" : undefined}
                        disabled={loading}
                        onFiles={(files) => void upload(files[0])}
                    />
                )}
                {value && !disabled && (compact || !imagesOnly) && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange("")}
                    >
                        {t(imagesOnly ? "Remove image" : "Remove file")}
                    </Button>
                )}
            </div>
            {variant !== "thumbnail" && (
                <details
                    className="text-xs text-muted-foreground"
                    open={!imagesOnly || (!listUrl && !uploadUrl)}
                >
                    <summary className="cursor-pointer py-1">
                        {t(
                            imagesOnly
                                ? "Image path or URL"
                                : "File path or URL",
                        )}
                    </summary>
                    <Input
                        id={listUrl && id ? `${id}-path` : id}
                        value={value}
                        disabled={disabled}
                        aria-label={t(imagesOnly ? "Image path" : "File path")}
                        onChange={(event) => onChange(event.target.value)}
                        placeholder={t(
                            imagesOnly
                                ? "Image path or URL"
                                : "File path or URL",
                        )}
                        className="mt-2 text-xs"
                    />
                </details>
            )}
            {!open && loading && <LoadingIndicator label={t("Uploading…")} />}
            {!open && error && (
                <p role="alert" className="text-xs text-destructive">
                    {error}
                </p>
            )}
            {listUrl && (
                <MediaLibraryDialog
                    open={open}
                    onOpenChange={setOpen}
                    listUrl={listUrl}
                    uploadUrl={uploadUrl}
                    imagesOnly={imagesOnly}
                    onInsert={([file]) => {
                        if (!file) return;
                        uploadSession.current.cancel();
                        setLoading(false);
                        setError("");
                        setImageFailed(false);
                        setPreview({ value: file.url, src: file.src });
                        onChange(file.url, file.src);
                    }}
                />
            )}
        </div>
    );
}
