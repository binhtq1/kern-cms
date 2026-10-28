import * as DialogPrimitive from "@radix-ui/react-dialog";
import * as DropdownMenuPrimitive from "@radix-ui/react-dropdown-menu";
import { Slot } from "@radix-ui/react-slot";
import { ChevronDown, X, type LucideIcon } from "lucide-react";
import {
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    type ReactNode,
} from "react";
import { cn } from "@admin/lib/utils";
import { useAdminTranslation } from "@admin/lib/translate";
import { Button } from "./button";
import { useAdminDirection } from "@admin/lib/use-admin-direction";

export function SidebarScrollArea({
    children,
    storageKey,
    collapsed,
}: {
    children: ReactNode;
    storageKey: string;
    collapsed: boolean;
}) {
    const area = useRef<HTMLDivElement>(null);
    const positionKey = `${storageKey}:${collapsed ? "compact" : "expanded"}`;
    const restoring = useRef(true);
    useLayoutEffect(() => {
        const element = area.current;
        if (!element) return;
        let position = 0;
        try {
            position =
                Number(
                    sessionStorage.getItem(positionKey) ??
                        (!collapsed
                            ? sessionStorage.getItem(storageKey)
                            : null),
                ) || 0;
        } catch {}
        restoring.current = true;
        element.scrollTop = position;
        const frame = requestAnimationFrame(() => {
            element.scrollTop = position;
            restoring.current = false;
        });
        return () => cancelAnimationFrame(frame);
    }, [positionKey, storageKey, collapsed]);
    return (
        <div
            ref={area}
            className="admin-navigation min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4"
            onScroll={(event) => {
                if (restoring.current || !event.currentTarget.clientHeight)
                    return;
                try {
                    sessionStorage.setItem(
                        positionKey,
                        String(event.currentTarget.scrollTop),
                    );
                } catch {}
            }}
        >
            {children}
        </div>
    );
}

export function Sidebar({
    open,
    onOpenChange,
    children,
    collapsed = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children: (compact: boolean) => ReactNode;
    collapsed?: boolean;
}) {
    const t = useAdminTranslation();
    const previousFocus = useRef<HTMLElement | null>(null);
    useEffect(() => {
        const desktop = window.matchMedia("(min-width: 768px)");
        const closeOnDesktop = () => {
            if (desktop.matches) {
                onOpenChange(false);
            }
        };
        desktop.addEventListener("change", closeOnDesktop);
        return () => desktop.removeEventListener("change", closeOnDesktop);
    }, [open, onOpenChange]);
    return (
        <>
            <aside
                id="admin-sidebar"
                className={cn(
                    "fixed inset-y-0 left-0 z-30 hidden flex-col border-r bg-sidebar text-sidebar-foreground md:flex",
                    collapsed ? "w-18" : "w-64",
                )}
            >
                {children(collapsed)}
            </aside>
            <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
                <DialogPrimitive.Portal>
                    <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-black/45" />
                    <DialogPrimitive.Content
                        id="admin-mobile-sidebar"
                        aria-describedby={undefined}
                        onOpenAutoFocus={() => {
                            previousFocus.current =
                                document.activeElement as HTMLElement;
                        }}
                        onCloseAutoFocus={(event) => {
                            event.preventDefault();
                            previousFocus.current?.focus();
                        }}
                        className="fixed inset-y-0 left-0 z-50 flex w-[min(20rem,calc(100vw-3rem))] flex-col border-r bg-sidebar text-sidebar-foreground shadow-xl outline-none"
                    >
                        <DialogPrimitive.Title className="sr-only">
                            {t("Administration")}
                        </DialogPrimitive.Title>
                        <DialogPrimitive.Close asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                className="absolute right-2 top-2"
                                aria-label={t("Close navigation")}
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </DialogPrimitive.Close>
                        {children(false)}
                    </DialogPrimitive.Content>
                </DialogPrimitive.Portal>
            </DialogPrimitive.Root>
        </>
    );
}

export function SidebarRailGroup({
    label,
    icon: Icon,
    active,
    count,
    children,
}: {
    label: string;
    icon: LucideIcon;
    active?: boolean;
    count?: number | null;
    children: ReactNode;
}) {
    const direction = useAdminDirection();
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const button = useRef<HTMLButtonElement>(null);
    const tabbing = useRef(false);
    return (
        <DropdownMenuPrimitive.Root
            dir={direction}
            modal={false}
            open={open}
            onOpenChange={setOpen}
        >
            <DropdownMenuPrimitive.Trigger asChild>
                <Button
                    ref={button}
                    variant="ghost"
                    size="icon"
                    className={cn(
                        "relative size-11 shrink-0 text-muted-foreground",
                        active &&
                            "bg-accent text-foreground ring-1 ring-border",
                    )}
                    title={label}
                    aria-label={
                        count ? `${label} (${count} ${t("Unread")})` : label
                    }
                >
                    <Icon aria-hidden="true" className="size-[18px]" />
                    {!!count && (
                        <span
                            aria-hidden="true"
                            className="absolute end-1 top-1 size-1.5 rounded-full bg-primary"
                        />
                    )}
                </Button>
            </DropdownMenuPrimitive.Trigger>
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.Content
                    side={direction === "rtl" ? "left" : "right"}
                    align="start"
                    sideOffset={12}
                    collisionPadding={8}
                    loop
                    onKeyDownCapture={(event) => {
                        if (event.key === "Tab") {
                            tabbing.current = true;
                            button.current?.focus();
                            setOpen(false);
                            event.stopPropagation();
                        }
                    }}
                    onCloseAutoFocus={(event) => {
                        if (tabbing.current) {
                            event.preventDefault();
                            tabbing.current = false;
                        }
                    }}
                    className="z-[70] max-h-[var(--radix-dropdown-menu-content-available-height)] w-64 max-w-[calc(100vw-1rem)] overflow-y-auto rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-md outline-none"
                >
                    <DropdownMenuPrimitive.Label className="px-3 py-2 text-xs font-semibold text-muted-foreground">
                        {label}
                    </DropdownMenuPrimitive.Label>
                    {children}
                </DropdownMenuPrimitive.Content>
            </DropdownMenuPrimitive.Portal>
        </DropdownMenuPrimitive.Root>
    );
}

export function SidebarItem({
    active,
    className,
    children,
    asChild = false,
    ...props
}: React.ComponentProps<"a"> & { active?: boolean; asChild?: boolean }) {
    const Component = asChild ? Slot : "a";
    return (
        <Component
            aria-current={active ? "page" : undefined}
            className={cn(
                "group relative flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring sm:min-h-9",
                active
                    ? "bg-accent font-medium text-foreground before:absolute before:inset-y-2.5 before:left-0 before:w-0.5 before:rounded-full before:bg-primary"
                    : "text-muted-foreground hover:bg-accent hover:text-foreground",
                className,
            )}
            {...props}
        >
            {children}
        </Component>
    );
}

export function SidebarGroup({
    label,
    count,
    active = false,
    children,
    icon: Icon,
    storageKey,
    forceOpen = false,
}: {
    label: string;
    count?: number | null;
    active?: boolean;
    children: ReactNode;
    icon?: LucideIcon;
    storageKey?: string;
    forceOpen?: boolean;
}) {
    const t = useAdminTranslation();
    const [expanded, setExpanded] = useState(() => {
        if (active || !storageKey) {
            return active;
        }
        try {
            return localStorage.getItem(storageKey) === "true";
        } catch {
            return false;
        }
    });
    useEffect(() => {
        if (active) {
            setExpanded(true);
        }
    }, [active]);
    return (
        <details
            className="group/sidebar-group"
            open={expanded || forceOpen}
            onToggle={(event) => {
                if (forceOpen) {
                    return;
                }
                const next = event.currentTarget.open;
                setExpanded(next);
                if (storageKey) {
                    try {
                        localStorage.setItem(storageKey, String(next));
                    } catch {}
                }
            }}
        >
            <summary
                onClick={(event) => {
                    if (forceOpen) {
                        event.preventDefault();
                    }
                }}
                className={cn(
                    "flex min-h-11 sm:min-h-9 cursor-pointer list-none items-center gap-3 rounded-md px-3 py-2 text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-details-marker]:hidden",
                    active
                        ? "font-medium text-foreground"
                        : "text-muted-foreground",
                )}
            >
                {Icon && (
                    <Icon aria-hidden="true" className="size-[18px] shrink-0" />
                )}
                <span className="min-w-0 flex-1 break-words">{label}</span>
                {!!count && (
                    <>
                        <span
                            aria-hidden="true"
                            className="rounded-full bg-primary/10 px-1.5 text-xs font-medium tabular-nums text-primary-ink"
                        >
                            {count}
                        </span>
                        <span className="sr-only">
                            {count} {t("Unread")}
                        </span>
                    </>
                )}
                <ChevronDown
                    aria-hidden="true"
                    className="size-3.5 shrink-0 transition-transform group-open/sidebar-group:rotate-180"
                />
            </summary>
            <div className="pb-1 pt-1">{children}</div>
        </details>
    );
}
