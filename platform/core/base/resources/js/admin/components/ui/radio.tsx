import { useAdminDirection } from "@admin/lib/use-admin-direction";
import type { ComponentProps } from "react";
import * as RadioGroupPrimitive from "@radix-ui/react-radio-group";
import { Circle } from "lucide-react";
import { cn } from "@admin/lib/utils";
import { useFieldControl } from "../field";

export function RadioGroup({
    className,
    ...props
}: ComponentProps<typeof RadioGroupPrimitive.Root>) {
    const field = useFieldControl(props.id);
    const direction = useAdminDirection();
    return (
        <RadioGroupPrimitive.Root
            {...field}
            {...props}
            dir={props.dir ?? direction}
            className={cn("grid gap-2", className)}
        />
    );
}

export function Radio({
    className,
    ...props
}: ComponentProps<typeof RadioGroupPrimitive.Item>) {
    return (
        <span className="inline-flex size-11 shrink-0 items-center justify-center align-middle sm:size-7">
            <RadioGroupPrimitive.Item
                data-slot="radio"
                {...props}
                className={cn(
                    "relative flex size-[18px] after:absolute after:-inset-[13px] after:content-[''] sm:after:-inset-[5px] cursor-pointer items-center justify-center rounded-full border border-input bg-background text-primary-ink outline-none focus-visible:ring-[3px] focus-visible:ring-ring/25 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:ring-2 aria-invalid:ring-destructive/30 data-[state=checked]:border-primary-ink",
                    className,
                )}
            >
                <RadioGroupPrimitive.Indicator>
                    <Circle className="size-2.5 fill-current" />
                </RadioGroupPrimitive.Indicator>
            </RadioGroupPrimitive.Item>
        </span>
    );
}
