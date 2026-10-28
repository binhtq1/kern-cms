import { Children, Fragment, isValidElement, type ReactNode } from "react";

export type SelectOption = {
    value: string;
    label: ReactNode;
    disabled?: boolean;
    group?: string;
};
export function selectOptions(
    children: ReactNode,
    group?: string,
    disabled = false,
): SelectOption[] {
    return Children.toArray(children).flatMap((child) => {
        if (
            !isValidElement<{
                value?: string | number;
                children?: ReactNode;
                label?: string;
                disabled?: boolean;
            }>(child)
        )
            return [];
        if (child.type === Fragment)
            return selectOptions(child.props.children, group, disabled);
        if (child.type === "optgroup")
            return selectOptions(
                child.props.children,
                child.props.label,
                disabled || child.props.disabled,
            );
        if (child.type !== "option") return [];
        return [
            {
                value: String(child.props.value ?? child.props.children ?? ""),
                label: child.props.children,
                group,
                disabled: disabled || child.props.disabled,
            },
        ];
    });
}

export function emptyOptionValue(
    options: SelectOption[],
    seed: string,
): string {
    let value = seed;
    while (options.some((option) => option.value === value)) value += "_";
    return value;
}
