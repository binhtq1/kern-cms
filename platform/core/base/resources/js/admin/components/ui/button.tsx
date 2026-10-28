import * as React from "react";
import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@admin/lib/utils";
import { Spinner } from "../loading-indicator";
export const buttonVariants = cva(
    "inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md border border-transparent text-sm font-medium transition-[background-color,color,border-color,box-shadow] outline-none focus-visible:ring-[3px] focus-visible:ring-ring/25 focus-visible:border-ring disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default:
                    "bg-primary text-primary-foreground hover:bg-primary/90",
                secondary:
                    "bg-secondary text-secondary-foreground hover:bg-secondary/75",
                outline:
                    "border-input bg-card hover:bg-accent hover:text-accent-foreground",
                ghost: "hover:bg-accent hover:text-accent-foreground",
                destructive:
                    "bg-red-700 text-white hover:bg-red-800 focus-visible:ring-destructive/25",
                link: "text-primary-ink underline-offset-4 hover:underline",
            },
            size: {
                default: "h-11 px-4 py-2 sm:h-9",
                sm: "h-11 px-3 text-xs sm:h-8",
                lg: "h-12 px-6 sm:h-10",
                icon: "size-11 sm:size-9",
            },
        },
        defaultVariants: { variant: "default", size: "default" },
    },
);
export function Button({
    className,
    variant,
    size,
    asChild = false,
    loading = false,
    disabled,
    children,
    type,
    onClickCapture,
    ...props
}: React.ComponentProps<"button"> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        loading?: boolean;
    }) {
    const Comp = asChild ? Slot : "button";
    return (
        <Comp
            data-slot="button"
            type={asChild ? undefined : (type ?? "button")}
            disabled={asChild ? undefined : disabled || loading}
            aria-disabled={asChild && (disabled || loading) ? true : undefined}
            aria-busy={loading || undefined}
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
            onClickCapture={(event) => {
                if (asChild && (disabled || loading)) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                onClickCapture?.(event);
            }}
        >
            {asChild ? (
                children
            ) : (
                <>
                    {loading && <Spinner />}
                    {children}
                </>
            )}
        </Comp>
    );
}
