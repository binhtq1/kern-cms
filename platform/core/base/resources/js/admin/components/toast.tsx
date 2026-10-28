import { handleInertiaNetworkError } from "@admin/lib/inertia-network-error";
import { useAdminTranslation } from "@admin/lib/translate";
import type { SharedProps } from "@admin/types";
import { router, usePage } from "@inertiajs/react";
import {
    CheckCircle2,
    CircleAlert,
    Info,
    TriangleAlert,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { SessionExpiredDialog } from "./session-expired-dialog";
import { Button } from "./ui/button";

type ToastSeverity = "success" | "error" | "warning" | "info";
type Toast = { id: number; message: string; severity: ToastSeverity };
let nextId = 0;
const listeners = new Set<(toast: Toast) => void>();
export function notify(
    message: string,
    severity: boolean | ToastSeverity = false,
) {
    if (!message) return;
    const toast: Toast = {
        id: ++nextId,
        message,
        severity:
            typeof severity === "boolean"
                ? severity
                    ? "error"
                    : "success"
                : severity,
    };
    listeners.forEach((listener) => listener(toast));
}
function ToastItem({ toast, dismiss }: { toast: Toast; dismiss: () => void }) {
    const t = useAdminTranslation();
    const [paused, setPaused] = useState(false);
    const dismissRef = useRef(dismiss);
    dismissRef.current = dismiss;
    useEffect(() => {
        if (paused) return;
        const timer = setTimeout(() => dismissRef.current(), 5000);
        return () => clearTimeout(timer);
    }, [paused]);
    const Icon = {
        error: CircleAlert,
        success: CheckCircle2,
        warning: TriangleAlert,
        info: Info,
    }[toast.severity];
    const tone = {
        error: "border-l-destructive",
        success: "border-l-primary",
        warning: "border-l-amber-500",
        info: "border-l-sky-500",
    }[toast.severity];
    return (
        <div
            role={
                toast.severity === "error" || toast.severity === "warning"
                    ? "alert"
                    : "status"
            }
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget))
                    setPaused(false);
            }}
            className={`pointer-events-auto flex items-start gap-3 rounded-md border border-l-4 bg-card p-4 text-card-foreground shadow-lg ${tone}`}
        >
            <Icon
                className={`mt-0.5 size-5 shrink-0 ${toast.severity === "error" ? "text-destructive" : toast.severity === "warning" ? "text-amber-600" : "text-primary-ink"}`}
            />
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">
                    {t(
                        {
                            error: "Error",
                            success: "Success",
                            warning: "Warning",
                            info: "Information",
                        }[toast.severity],
                    )}
                </p>
                <p className="mt-1 break-words text-sm">{toast.message}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                className="-mt-1 -mr-1 size-7 shrink-0"
                aria-label={t("Dismiss notification")}
                onClick={dismiss}
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}
export function ToastHost() {
    const { flash, errors } = usePage<SharedProps>().props;
    const t = useAdminTranslation();
    const [toasts, setToasts] = useState<Toast[]>([]);
    useEffect(() => {
        const add = (toast: Toast) =>
            setToasts((current) =>
                [
                    ...current.filter(
                        (item) =>
                            item.message !== toast.message ||
                            item.severity !== toast.severity,
                    ),
                    toast,
                ].slice(-4),
            );
        listeners.add(add);
        return () => {
            listeners.delete(add);
        };
    }, []);
    useEffect(
        () =>
            router.on("networkError", (event) => {
                handleInertiaNetworkError(event, () =>
                    notify(
                        t(
                            "Unable to connect to the server. Please check your connection and try again.",
                        ),
                        true,
                    ),
                );
            }),
        [t],
    );
    // Page props change on a completed Inertia visit, even for the same flash text.
    useEffect(() => {
        if (flash?.success) notify(flash.success);
        if (flash?.error) notify(flash.error, true);
        if (errors?.message) notify(errors.message, true);
        else if (
            errors?._request &&
            Object.keys(errors).every((key) => key === "_request")
        ) {
            if (!flash?.error) notify(errors._request, true);
        } else if (errors && Object.keys(errors).length)
            notify(t("Please check the highlighted fields."), true);
    }, [flash, errors, t]);
    return (
        <>
            <SessionExpiredDialog
                showError={(message) => notify(message, true)}
            />
            {createPortal(
                <div
                    aria-live="polite"
                    aria-relevant="additions"
                    aria-label={t("Notifications")}
                    className="pointer-events-none fixed right-4 bottom-4 z-[200] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3"
                >
                    {toasts.map((toast) => (
                        <ToastItem
                            key={toast.id}
                            toast={toast}
                            dismiss={() =>
                                setToasts((items) =>
                                    items.filter(
                                        (item) => item.id !== toast.id,
                                    ),
                                )
                            }
                        />
                    ))}
                </div>,
                document.body,
            )}
        </>
    );
}
