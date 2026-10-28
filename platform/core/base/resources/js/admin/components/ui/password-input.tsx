import { useId, useState, type ComponentProps } from "react";
import { Eye, EyeOff } from "lucide-react";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import { Button } from "./button";
import { Input } from "./input";

export function PasswordInput({
    id,
    className,
    disabled,
    ...props
}: Omit<ComponentProps<typeof Input>, "type">) {
    const t = useAdminTranslation();
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const [visible, setVisible] = useState(false);
    const label = t(visible ? "Hide password" : "Show password");
    const Icon = visible ? EyeOff : Eye;

    return (
        <div className="relative">
            <Input
                {...props}
                id={inputId}
                type={visible ? "text" : "password"}
                disabled={disabled}
                className={cn("pe-12", className)}
            />
            <Button
                variant="ghost"
                size="icon"
                className="absolute end-0 top-0 text-muted-foreground"
                aria-label={label}
                title={label}
                aria-controls={inputId}
                aria-pressed={visible}
                disabled={disabled}
                onClick={() => setVisible((current) => !current)}
            >
                <Icon aria-hidden="true" />
            </Button>
        </div>
    );
}
