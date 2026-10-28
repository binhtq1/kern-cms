import type { ComponentProps } from "react";
import { cn } from "@admin/lib/utils";
export function FilterToolbar({ className, ...props }: ComponentProps<"form">) {
    return (
        <form
            className={cn(
                "flex flex-wrap items-center gap-3 border-b p-4",
                className,
            )}
            {...props}
        />
    );
}
