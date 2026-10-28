import { forwardRef, type ComponentProps } from "react";
import * as CheckboxPrimitive from "@radix-ui/react-checkbox";
import { Check, Minus } from "lucide-react";
import { cn } from "@admin/lib/utils";
import { useFieldControl } from "../field";

export const Checkbox = forwardRef<
    HTMLButtonElement,
    ComponentProps<typeof CheckboxPrimitive.Root> & { indeterminate?: boolean }
>(function Checkbox(
    { className, indeterminate = false, checked, ...props },
    ref,
) {
    const field = useFieldControl(props.id);
    return (
        <span className="inline-flex size-11 shrink-0 items-center justify-center align-middle sm:size-7">
            <CheckboxPrimitive.Root
                ref={ref}
                data-slot="checkbox"
                {...field}
                {...props}
                checked={indeterminate ? "indeterminate" : checked}
                className={cn(
                    "peer relative flex size-[18px] after:absolute after:-inset-[13px] after:content-[''] sm:after:-inset-[5px] shrink-0 cursor-pointer items-center justify-center rounded border border-input bg-background outline-none focus-visible:ring-[3px] focus-visible:ring-ring/25 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:ring-2 aria-invalid:ring-destructive/30 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground",
                    className,
                )}
            >
                <CheckboxPrimitive.Indicator>
                    {indeterminate || checked === "indeterminate" ? (
                        <Minus className="size-3.5" />
                    ) : (
                        <Check className="size-3.5" />
                    )}
                </CheckboxPrimitive.Indicator>
            </CheckboxPrimitive.Root>
        </span>
    );
});
