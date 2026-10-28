import type { ComponentProps } from "react";
import { cn } from "@admin/lib/utils";
export function Table({ className, ...props }: ComponentProps<"table">) {
    return (
        <table
            data-slot="table"
            className={cn("w-full text-sm", className)}
            {...props}
        />
    );
}
export function TableHeader({ className, ...props }: ComponentProps<"thead">) {
    return (
        <thead
            className={cn(
                "bg-muted/40 text-start text-xs text-muted-foreground [&_th]:px-4 [&_th]:py-3",
                className,
            )}
            {...props}
        />
    );
}
export function TableBody(props: ComponentProps<"tbody">) {
    return <tbody {...props} />;
}
export function TableRow({ className, ...props }: ComponentProps<"tr">) {
    return (
        <tr
            className={cn(
                "border-b last:border-0 hover:bg-muted/30",
                className,
            )}
            {...props}
        />
    );
}
export function TableHead({
    className,
    scope = "col",
    ...props
}: ComponentProps<"th">) {
    return (
        <th
            scope={scope}
            className={cn("px-4 py-3 font-medium", className)}
            {...props}
        />
    );
}
export function TableCell({ className, ...props }: ComponentProps<"td">) {
    return (
        <td className={cn("px-4 py-3 align-middle", className)} {...props} />
    );
}
