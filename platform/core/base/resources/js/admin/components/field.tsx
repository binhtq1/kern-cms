import { createContext, useContext, useId, type ReactNode } from "react";
import { cn } from "@admin/lib/utils";
const FieldContext = createContext<{
    id: string;
    error?: string;
    description?: ReactNode;
} | null>(null);
export function useFieldControl(id?: string) {
    const field = useContext(FieldContext);
    if (!field || id !== field.id) {
        return {};
    }
    return {
        "aria-invalid": field.error ? true : undefined,
        "aria-describedby":
            [
                field.description ? `${id}-description` : "",
                field.error ? `${id}-error` : "",
            ]
                .filter(Boolean)
                .join(" ") || undefined,
    };
}
export function Field({
    label,
    error,
    children,
    required,
    description,
    group = false,
    className,
}: {
    label: string;
    error?: string;
    children: (id: string) => ReactNode;
    required?: boolean;
    description?: ReactNode;
    group?: boolean;
    className?: string;
}) {
    const id = useId();
    const Container = group ? "fieldset" : "div";
    const labelContent = (
        <>
            {label}
            {required && (
                <span aria-hidden="true" className="ml-1 text-destructive">
                    *
                </span>
            )}
        </>
    );
    return (
        <FieldContext.Provider value={{ id, error, description }}>
            <Container
                className={cn("min-w-0 space-y-2", className)}
                data-invalid={!!error || undefined}
                aria-invalid={group && error ? true : undefined}
                aria-describedby={
                    group
                        ? [
                              description ? `${id}-description` : "",
                              error ? `${id}-error` : "",
                          ]
                              .filter(Boolean)
                              .join(" ") || undefined
                        : undefined
                }
            >
                {group ? (
                    <legend
                        id={`${id}-label`}
                        className="mb-2 block text-sm font-medium leading-5"
                    >
                        {labelContent}
                    </legend>
                ) : (
                    <label
                        id={`${id}-label`}
                        htmlFor={id}
                        className="block text-sm font-medium leading-5"
                    >
                        {labelContent}
                    </label>
                )}
                {children(id)}
                {description && (
                    <div
                        id={`${id}-description`}
                        className="text-xs leading-relaxed text-muted-foreground [overflow-wrap:anywhere]"
                    >
                        {description}
                    </div>
                )}
                {error && (
                    <p
                        id={`${id}-error`}
                        role="alert"
                        className="text-xs font-medium text-destructive [overflow-wrap:anywhere]"
                    >
                        {error}
                    </p>
                )}
            </Container>
        </FieldContext.Provider>
    );
}
