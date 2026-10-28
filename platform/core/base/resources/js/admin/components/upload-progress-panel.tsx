import { useEffect, useId, useRef, useState } from "react";
import {
    CheckCircle2,
    CircleX,
    CircleStop,
    ChevronDown,
    ChevronUp,
    Clock3,
    File,
    RotateCcw,
    X,
} from "lucide-react";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import {
    formatUploadSize,
    type UploadProgressItem,
} from "@admin/lib/upload-progress";
import { LoadingIndicator } from "./loading-indicator";
import { Button } from "./ui/button";

export function UploadProgressPanel({
    items,
    onCancel,
    onDismiss,
    onRetry,
    retryDisabled = false,
    inline = false,
}: {
    items: UploadProgressItem[];
    onCancel: () => void;
    onDismiss: () => void;
    onRetry?: (id: string) => void;
    retryDisabled?: boolean;
    inline?: boolean;
}) {
    const t = useAdminTranslation();
    const titleId = useId();
    const panel = useRef<HTMLElement>(null);
    const listId = useId();
    const [collapsed, setCollapsed] = useState(false);
    const toggleLabel = t(
        collapsed ? "Expand upload progress" : "Collapse upload progress",
    );
    const pending = items.some(
        (item) => item.status === "queued" || item.status === "uploading",
    );
    const completed = items.filter((item) => item.status === "uploaded").length;
    const successful = items.length > 0 && completed === items.length;
    useEffect(() => {
        if (!successful) return;
        const timer = window.setTimeout(() => {
            if (!panel.current?.matches(":focus-within")) onDismiss();
        }, 5000);
        return () => window.clearTimeout(timer);
    }, [successful, onDismiss]);
    if (!items.length) return null;
    return (
        <section
            ref={panel}
            aria-labelledby={titleId}
            className={cn(
                "overflow-hidden rounded-lg border bg-card text-card-foreground shadow-lg",
                inline
                    ? "mb-4"
                    : "fixed bottom-4 end-4 z-40 w-[calc(100%-2rem)] max-w-[600px]",
            )}
        >
            <header className="flex items-center justify-between gap-3 border-b px-4 py-3">
                <div className="min-w-0">
                    <h2 id={titleId} className="text-sm font-semibold">
                        {t("Upload progress")}
                    </h2>
                    <p
                        role="status"
                        className="mt-1 text-xs text-muted-foreground"
                    >
                        {t(":completed / :total files uploaded", {
                            completed,
                            total: items.length,
                        })}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {pending && (
                        <Button
                            variant="ghost"
                            size="icon"
                            title={t("Cancel upload")}
                            aria-label={t("Cancel upload")}
                            onClick={onCancel}
                        >
                            <CircleStop aria-hidden="true" />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        title={toggleLabel}
                        aria-label={toggleLabel}
                        aria-expanded={!collapsed}
                        aria-controls={listId}
                        onClick={() => setCollapsed((value) => !value)}
                    >
                        {collapsed ? (
                            <ChevronUp aria-hidden="true" />
                        ) : (
                            <ChevronDown aria-hidden="true" />
                        )}
                    </Button>
                    {!pending && (
                        <Button
                            variant="ghost"
                            size="icon"
                            title={t("Close upload progress")}
                            aria-label={t("Close upload progress")}
                            onClick={onDismiss}
                        >
                            <X aria-hidden="true" />
                        </Button>
                    )}
                </div>
            </header>
            <ul
                id={listId}
                hidden={collapsed}
                className="max-h-[min(40dvh,20rem)] divide-y overflow-y-auto overscroll-contain"
            >
                {items.map((item) => (
                    <li key={item.id} className="space-y-2 px-4 py-3">
                        <div className="flex items-start gap-3">
                            <File
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="break-words text-sm font-medium [overflow-wrap:anywhere]">
                                    {item.name}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {formatUploadSize(item.size)}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className="flex items-center gap-1.5 text-xs tabular-nums">
                                    {item.status === "uploading" ? (
                                        <>
                                            <LoadingIndicator
                                                label={`${item.name}: ${t("Upload progress")}`}
                                            />
                                            {item.percent}%
                                        </>
                                    ) : item.status === "uploaded" ? (
                                        <>
                                            <CheckCircle2
                                                aria-hidden="true"
                                                className="size-4 text-primary-ink"
                                            />
                                            {t("Uploaded")}
                                        </>
                                    ) : item.status === "error" ? (
                                        <>
                                            <CircleX
                                                aria-hidden="true"
                                                className="size-4 text-destructive"
                                            />
                                            {t("Upload failed")}
                                        </>
                                    ) : item.status === "cancelled" ? (
                                        <>
                                            <X
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                            {t("Cancelled")}
                                        </>
                                    ) : (
                                        <>
                                            <Clock3
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                            {t("Queued")}
                                        </>
                                    )}
                                </span>
                                {onRetry && (
                                    <div className="flex size-11 shrink-0 items-center justify-center sm:size-9">
                                        {item.status === "error" && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    pending || retryDisabled
                                                }
                                                title={t("Retry")}
                                                aria-label={t(
                                                    "Retry upload: :name",
                                                    { name: item.name },
                                                )}
                                                onClick={() => onRetry(item.id)}
                                            >
                                                <RotateCcw aria-hidden="true" />
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                        {item.status === "uploading" && (
                            <progress
                                max={100}
                                value={item.percent}
                                aria-label={`${item.name}: ${t("Upload progress")}`}
                                className="block h-1.5 w-full overflow-hidden rounded-full bg-muted accent-primary [&::-webkit-progress-bar]:bg-muted [&::-webkit-progress-value]:bg-primary [&::-moz-progress-bar]:bg-primary"
                            />
                        )}
                        {item.error && (
                            <p
                                role="alert"
                                className="break-words text-xs text-destructive [overflow-wrap:anywhere]"
                            >
                                {item.error}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
