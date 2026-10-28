import { useAdminTranslation } from "@admin/lib/translate";
import * as DialogPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import {
    useId,
    useRef,
    useState,
    useLayoutEffect,
    type ReactNode,
    type RefObject,
} from "react";
import { cn } from "@admin/lib/utils";
import { Button } from "./button";
const activeDialogs = new Map<string, number>();

export function Dialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    size = "lg",
    initialFocusRef,
    returnFocusRef,
    footer,
    closeDisabled = false,
}: {
    open: boolean;
    onOpenChange: (value: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    size?: "sm" | "lg" | "xl" | "full";
    initialFocusRef?: RefObject<HTMLElement | null>;
    returnFocusRef?: RefObject<HTMLElement | null>;
    footer?: ReactNode;
    closeDisabled?: boolean;
}) {
    const t = useAdminTranslation();
    const contentRef = useRef<HTMLDivElement>(null);
    const previousFocus = useRef<HTMLElement | null>(null);
    const descriptionId = useId();
    const [layer, setLayer] = useState(50);
    useLayoutEffect(() => {
        if (!open) return;
        const next = Math.max(48, ...activeDialogs.values()) + 2;
        activeDialogs.set(descriptionId, next);
        setLayer(next);
        return () => {
            activeDialogs.delete(descriptionId);
        };
    }, [open, descriptionId]);
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay
                    style={{ zIndex: layer }}
                    className="fixed inset-0 bg-black/45"
                />
                <DialogPrimitive.Content
                    ref={contentRef}
                    style={{ zIndex: layer + 1 }}
                    aria-describedby={description ? descriptionId : undefined}
                    onOpenAutoFocus={(event) => {
                        previousFocus.current =
                            document.activeElement as HTMLElement;
                        if (initialFocusRef?.current) {
                            event.preventDefault();
                            initialFocusRef.current.focus();
                        } else {
                            const target =
                                contentRef.current?.querySelector<HTMLElement>(
                                    "[data-autofocus], input:not([type=hidden]):not([disabled]), textarea:not([disabled])",
                                );
                            if (target) {
                                event.preventDefault();
                                target.focus();
                            }
                        }
                    }}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        const target = returnFocusRef?.current?.isConnected
                            ? returnFocusRef.current
                            : previousFocus.current;
                        if (target?.isConnected) {
                            target.focus();
                        }
                    }}
                    className={cn(
                        "fixed top-1/2 left-1/2 flex flex-col max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-lg border bg-card shadow-xl outline-none",
                        {
                            sm: "max-w-md",
                            lg: "max-w-3xl",
                            xl: "max-w-5xl",
                            full: "max-w-[1600px]",
                        }[size],
                    )}
                >
                    <div className="relative shrink-0 border-b p-5 sm:px-6 sm:py-4">
                        <DialogPrimitive.Title className="break-words [overflow-wrap:anywhere] pr-10 text-lg font-semibold tracking-tight">
                            {title}
                        </DialogPrimitive.Title>
                        {description && (
                            <DialogPrimitive.Description
                                id={descriptionId}
                                className="mt-2 pr-6 text-sm leading-relaxed text-muted-foreground"
                            >
                                {description}
                            </DialogPrimitive.Description>
                        )}
                        <DialogPrimitive.Close asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="absolute top-2 right-2"
                                aria-label={t("Close")}
                                disabled={closeDisabled}
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </DialogPrimitive.Close>
                    </div>
                    <div className="min-h-0 overflow-y-auto overscroll-contain p-5 sm:p-6">
                        {children}
                    </div>
                    {footer && (
                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t bg-card px-5 py-3 sm:px-6">
                            {footer}
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
