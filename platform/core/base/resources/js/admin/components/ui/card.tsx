import type { ComponentProps } from "react";
import { cn } from "@admin/lib/utils";

export function Card({ className, ...props }: ComponentProps<"section">) {
    return (
        <section
            data-slot="card"
            className={cn(
                "min-w-0 rounded-lg border bg-card text-card-foreground",
                className,
            )}
            {...props}
        />
    );
}
export function CardHeader({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="card-header"
            className={cn("space-y-1.5 border-b px-5 py-3", className)}
            {...props}
        />
    );
}
export function CardTitle({ className, ...props }: ComponentProps<"h2">) {
    return (
        <h2
            data-slot="card-title"
            className={cn("text-sm font-semibold tracking-tight", className)}
            {...props}
        />
    );
}
export function CardDescription({ className, ...props }: ComponentProps<"p">) {
    return (
        <p
            className={cn(
                "text-sm leading-relaxed text-muted-foreground",
                className,
            )}
            {...props}
        />
    );
}
export function CardContent({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="card-content"
            className={cn("p-5", className)}
            {...props}
        />
    );
}
export function CardFooter({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="card-footer"
            className={cn(
                "flex items-center gap-3 border-t px-5 py-3",
                className,
            )}
            {...props}
        />
    );
}
