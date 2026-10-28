import { LoadingIndicator } from "@admin/components/loading-indicator";
import { cn } from "@admin/lib/utils";

export function WidgetLoading({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    return (
        <LoadingIndicator
            label={label}
            className={cn(
                "flex min-h-48 items-center justify-center text-muted-foreground",
                className,
            )}
            iconClassName="size-5"
        />
    );
}
