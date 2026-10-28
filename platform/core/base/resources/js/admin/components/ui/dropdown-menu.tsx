import { useRef, useState, type ComponentProps, type ReactNode } from "react";
import * as DropdownMenuPrimitive from "@radix-ui/react-dropdown-menu";
import { MoreHorizontal } from "lucide-react";
import { Button } from "./button";
import { cn } from "@admin/lib/utils";
import { useAdminDirection } from "@admin/lib/use-admin-direction";

export function DropdownMenu({
    label,
    children,
    trigger,
    align = "end",
    disabled = false,
    loading = false,
}: {
    label: string;
    children: ReactNode;
    trigger?: ReactNode;
    align?: "start" | "end";
    disabled?: boolean;
    loading?: boolean;
}) {
    const direction = useAdminDirection();
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
                    data-slot="dropdown-menu-trigger"
                    ref={button}
                    variant="ghost"
                    size={trigger ? "default" : "icon"}
                    aria-label={label}
                    disabled={disabled}
                    loading={loading}
                >
                    {trigger ?? <MoreHorizontal aria-hidden="true" />}
                </Button>
            </DropdownMenuPrimitive.Trigger>
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.Content
                    data-slot="dropdown-menu-content"
                    align={align}
                    sideOffset={6}
                    collisionPadding={8}
                    loop
                    className="z-[70] max-h-[var(--radix-dropdown-menu-content-available-height)] min-w-48 max-w-[calc(100vw-1rem)] overflow-y-auto rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-md outline-none"
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
                >
                    {children}
                </DropdownMenuPrimitive.Content>
            </DropdownMenuPrimitive.Portal>
        </DropdownMenuPrimitive.Root>
    );
}

export function DropdownMenuItem({
    className,
    destructive,
    disabled,
    onClickCapture,
    onKeyDownCapture,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Item> & {
    destructive?: boolean;
}) {
    return (
        <DropdownMenuPrimitive.Item
            {...props}
            data-slot="dropdown-menu-item"
            disabled={disabled}
            className={cn(
                "flex min-h-11 w-full cursor-default items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm outline-none data-[highlighted]:bg-accent focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring/60 data-[disabled]:pointer-events-none data-[disabled]:opacity-50 sm:min-h-9 [&_svg]:size-4",
                destructive &&
                    "text-destructive data-[highlighted]:bg-red-50 dark:data-[highlighted]:bg-red-950/50",
                className,
            )}
            onClickCapture={(event) => {
                if (disabled) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                onClickCapture?.(event);
            }}
            onKeyDownCapture={(event) => {
                if (disabled && ["Enter", " "].includes(event.key)) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                onKeyDownCapture?.(event);
            }}
        />
    );
}

export function DropdownMenuSeparator() {
    return (
        <DropdownMenuPrimitive.Separator
            data-slot="dropdown-menu-separator"
            className="my-1.5 border-t"
        />
    );
}
