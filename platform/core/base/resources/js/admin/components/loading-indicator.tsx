import type { ComponentProps } from "react";
import { LoaderCircle } from "lucide-react";
import { cn } from "@admin/lib/utils";

export function Spinner({
    className,
    ...props
}: ComponentProps<typeof LoaderCircle>) {
    return (
        <LoaderCircle
            {...props}
            aria-hidden="true"
            className={cn(
                "size-4 shrink-0 animate-spin motion-reduce:animate-none",
                className,
            )}
        />
    );
}

export function LoadingIndicator({
    label,
    className,
    iconClassName,
    announce = true,
}: {
    label: string;
    className?: string;
    iconClassName?: string;
    announce?: boolean;
}) {
    return (
        <span
            role={announce ? "status" : undefined}
            className={cn(
                "inline-flex items-center justify-center text-muted-foreground",
                className,
            )}
        >
            <Spinner className={iconClassName} />
            <span className="sr-only">{label}</span>
        </span>
    );
}
