import { CloudUpload, FileIcon, X } from "lucide-react";
import { useId, useRef, useState } from "react";
import { useAdminTranslation } from "@admin/lib/translate";
import { formatUploadSize } from "@admin/lib/upload-progress";
import { cn } from "@admin/lib/utils";
import { useFieldControl } from "./field";
import { Spinner } from "./loading-indicator";
import { Button } from "./ui/button";

function acceptsFile(file: File, accept: string) {
    const name = file.name.toLowerCase();
    const type = file.type.toLowerCase();

    return accept.split(",").some((specifier) => {
        const token = specifier.trim().toLowerCase();
        if (!token) return false;
        if (token.startsWith(".")) return name.endsWith(token);
        if (token.endsWith("/*")) return type.startsWith(token.slice(0, -1));
        return type === token;
    });
}

export function FileUploadField({
    id,
    label,
    accept,
    formats,
    maxBytes,
    file,
    disabled = false,
    loading = false,
    onSelect,
    onRemove,
}: {
    id?: string;
    label?: string;
    accept: string;
    formats: string;
    maxBytes?: number;
    file?: File | null;
    disabled?: boolean;
    loading?: boolean;
    onSelect: (file: File) => void;
    onRemove?: () => void;
}) {
    const t = useAdminTranslation();
    const field = useFieldControl(id);
    const hintId = useId();
    const errorId = useId();
    const input = useRef<HTMLInputElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const dragDepth = useRef(0);
    const [dragging, setDragging] = useState(false);
    const [error, setError] = useState("");
    const inactive = disabled || loading;
    const invalid = !!error || field["aria-invalid"];
    const isDropTarget = dragging && !inactive;

    function select(files: FileList | null) {
        if (inactive || !files?.length) return;

        const next = files[0];
        if (files.length > 1) {
            setError(t("Choose one file at a time."));
        } else if (!acceptsFile(next, accept)) {
            setError(t("Unsupported file type. Choose :formats.", { formats }));
        } else if (maxBytes !== undefined && next.size > maxBytes) {
            setError(
                t("Choose a file no larger than :size.", {
                    size: formatUploadSize(maxBytes),
                }),
            );
        } else {
            setError("");
            onSelect(next);
        }
    }

    const Icon = file ? FileIcon : CloudUpload;
    return (
        <div className="@container min-w-0 space-y-2">
            <div
                className={cn(
                    "flex min-h-20 flex-wrap items-center gap-3 rounded-lg border bg-card p-3 motion-safe:transition-colors",
                    file
                        ? "border-input"
                        : "border-dashed border-input bg-muted/20",
                    !inactive && "focus-within:border-ring",
                    invalid && "border-destructive",
                    isDropTarget && "border-primary bg-primary/10",
                )}
                onDragEnter={(event) => {
                    if (!event.dataTransfer.types.includes("Files")) return;
                    dragDepth.current += 1;
                    if (!inactive) setDragging(true);
                }}
                onDragOver={(event) => {
                    if (!event.dataTransfer.types.includes("Files")) return;
                    event.preventDefault();
                    event.dataTransfer.dropEffect = inactive ? "none" : "copy";
                }}
                onDragLeave={() => {
                    dragDepth.current = Math.max(0, dragDepth.current - 1);
                    if (!dragDepth.current) setDragging(false);
                }}
                onDrop={(event) => {
                    if (!event.dataTransfer.types.includes("Files")) return;
                    event.preventDefault();
                    event.stopPropagation();
                    dragDepth.current = 0;
                    setDragging(false);
                    select(event.dataTransfer.files);
                }}
            >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary-ink">
                    <Icon aria-hidden="true" className="size-5" />
                </span>
                <div className="min-w-0 flex-[1_1_8rem] space-y-0.5">
                    <p
                        aria-live="polite"
                        aria-atomic="true"
                        className="text-sm font-medium [overflow-wrap:anywhere]"
                    >
                        {loading
                            ? t("Reading file…")
                            : isDropTarget
                              ? t("Drop the file here")
                              : (file?.name ?? t("Drag a file here"))}
                    </p>
                    {file && (
                        <p className="text-xs tabular-nums text-muted-foreground">
                            {formatUploadSize(file.size)}
                        </p>
                    )}
                    <p
                        id={hintId}
                        className={cn(
                            "text-xs text-muted-foreground",
                            file && "sr-only",
                        )}
                    >
                        {formats}
                        {maxBytes !== undefined && (
                            <>
                                {" · "}
                                {t("Up to :size", {
                                    size: formatUploadSize(maxBytes),
                                })}
                            </>
                        )}
                    </p>
                </div>
                <div className="flex w-full items-center gap-1 @sm:w-auto">
                    <Button
                        id={id}
                        ref={trigger}
                        data-autofocus
                        variant="outline"
                        className="flex-1"
                        disabled={disabled}
                        aria-disabled={loading || undefined}
                        aria-busy={loading || undefined}
                        aria-labelledby={[
                            id && `${id}-label`,
                            label && `${hintId}-label`,
                            `${hintId}-action`,
                        ]
                            .filter(Boolean)
                            .join(" ")}
                        aria-invalid={invalid}
                        aria-describedby={[
                            hintId,
                            field["aria-describedby"],
                            error && errorId,
                        ]
                            .filter(Boolean)
                            .join(" ")}
                        onClick={() => {
                            if (!inactive) input.current?.click();
                        }}
                    >
                        {loading && <Spinner />}
                        {label && (
                            <span id={`${hintId}-label`} className="sr-only">
                                {label}
                            </span>
                        )}
                        <span id={`${hintId}-action`}>
                            {file ? t("Change file") : t("Choose file")}
                        </span>
                    </Button>
                    {file && onRemove && (
                        <Button
                            variant="ghost"
                            size="icon"
                            title={t("Remove file")}
                            aria-label={t("Remove file")}
                            disabled={inactive}
                            onClick={() => {
                                setError("");
                                onRemove();
                                trigger.current?.focus();
                            }}
                        >
                            <X aria-hidden="true" />
                        </Button>
                    )}
                </div>
                <input
                    ref={input}
                    type="file"
                    accept={accept}
                    disabled={inactive}
                    hidden
                    onChange={(event) => {
                        select(event.currentTarget.files);
                        event.currentTarget.value = "";
                    }}
                />
            </div>
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs font-medium text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
