import * as React from "react";
import { DateTimePicker } from "../date-time-picker";
import { cn } from "@admin/lib/utils";
import { useFieldControl } from "../field";
export const controlStyles =
    "w-full min-w-0 rounded-md border border-input bg-card text-foreground transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground/80 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20 disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60 [&[readonly]]:bg-muted/50 aria-invalid:border-destructive aria-invalid:focus-visible:ring-destructive/20";
export function Input({ className, ...props }: React.ComponentProps<"input">) {
    const field = useFieldControl(props.id);
    const Component = ["date", "time", "datetime-local"].includes(
        props.type ?? "",
    )
        ? DateTimePicker
        : "input";
    return (
        <Component
            data-slot="input"
            {...field}
            className={cn(
                controlStyles,
                "flex h-11 px-3 py-2 text-base sm:h-9 sm:text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs file:font-medium",
                className,
            )}
            {...props}
        />
    );
}
export function Textarea({
    className,
    ...props
}: React.ComponentProps<"textarea">) {
    const field = useFieldControl(props.id);
    return (
        <textarea
            data-slot="textarea"
            {...field}
            className={cn(
                controlStyles,
                "flex min-h-28 resize-y px-3 py-2.5 text-base leading-relaxed sm:text-sm",
                className,
            )}
            {...props}
        />
    );
}
