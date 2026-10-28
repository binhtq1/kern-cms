import type { ComponentProps } from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@admin/lib/utils";
const variants = cva(
    "inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium",
    {
        variants: {
            variant: {
                default:
                    "border-transparent bg-secondary text-secondary-foreground",
                success:
                    "border-emerald-200/70 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300",
                warning:
                    "border-amber-200/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200",
                destructive:
                    "border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/50 text-destructive",
                outline: "text-muted-foreground",
            },
        },
        defaultVariants: { variant: "default" },
    },
);
export function Badge({
    className,
    variant,
    ...props
}: ComponentProps<"span"> & VariantProps<typeof variants>) {
    return (
        <span
            data-slot="badge"
            className={cn(variants({ variant }), className)}
            {...props}
        />
    );
}
