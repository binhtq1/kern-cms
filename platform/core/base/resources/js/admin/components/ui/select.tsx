import { useAdminTranslation } from "@admin/lib/translate";
import { useAdminDirection } from "@admin/lib/use-admin-direction";
import { cn, normalizeSearch } from "@admin/lib/utils";
import * as SelectPrimitive from "@radix-ui/react-select";
import { Check, ChevronDown, ChevronUp, Search } from "lucide-react";
import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type ComponentProps,
} from "react";
import { useFieldControl } from "../field";
import { Button } from "./button";
import { Dialog } from "./dialog";
import { controlStyles } from "./input";
import {
    emptyOptionValue,
    selectOptions,
    type SelectOption,
} from "./select-options";

type SelectProps = Omit<ComponentProps<"select">, "onChange"> & {
    searchable?: boolean;
    containerClassName?: string;
    onValueChange?: (value: string) => void;
    onValuesChange?: (values: string[]) => void;
};

/** Multiple selection keeps the platform listbox and its modifier-key behavior. */
export function NativeSelect({
    className,
    containerClassName,
    children,
    onValueChange,
    onValuesChange,
    ...props
}: SelectProps) {
    const field = useFieldControl(props.id);
    const listbox = props.multiple || (props.size ?? 0) > 1;
    return (
        <div className={cn("relative min-w-0", containerClassName)}>
            <select
                data-slot="select"
                {...field}
                {...props}
                className={cn(
                    controlStyles,
                    "h-11 appearance-none px-3 pr-9 text-base sm:h-9 sm:text-sm",
                    listbox && "h-auto min-h-28 py-2 pr-3",
                    className,
                )}
                onChange={(event) =>
                    props.multiple
                        ? onValuesChange?.(
                              Array.from(
                                  event.target.selectedOptions,
                                  (option) => option.value,
                              ),
                          )
                        : onValueChange?.(event.target.value)
                }
            >
                {children}
            </select>
            {!listbox && (
                <ChevronDown
                    aria-hidden="true"
                    className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
            )}
        </div>
    );
}

function SingleSelect(props: SelectProps) {
    const field = useFieldControl(props.id);
    const direction = useAdminDirection();
    const seed = useId();
    const options = selectOptions(props.children);
    const emptyValue = emptyOptionValue(options, seed);
    const [localValue, setLocalValue] = useState(() =>
        String(
            props.defaultValue ??
                options.find((option) => !option.disabled)?.value ??
                "",
        ),
    );
    if (props.multiple || (props.size ?? 0) > 1)
        return <NativeSelect {...props} />;
    const {
        children,
        value,
        onValueChange,
        disabled,
        required,
        name,
        form,
        autoComplete,
        className,
        containerClassName,
        id,
        title,
        onBlur,
        onFocus,
        tabIndex,
        "aria-label": ariaLabel,
        "aria-labelledby": ariaLabelledby,
        "aria-describedby": ariaDescribedby,
        "aria-invalid": ariaInvalid,
    } = props;
    const placeholder =
        options.find((option) => option.value === "")?.label ?? "";
    const renderOption = (option: SelectOption) => (
        <SelectPrimitive.Item
            key={option.value}
            value={option.value || emptyValue}
            disabled={option.disabled}
            className="relative flex min-h-9 cursor-default select-none items-center rounded-sm py-2 pl-8 pr-3 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
        >
            <span className="absolute left-2 flex size-4 items-center justify-center">
                <SelectPrimitive.ItemIndicator>
                    <Check className="size-4" />
                </SelectPrimitive.ItemIndicator>
            </span>
            <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
        </SelectPrimitive.Item>
    );
    const groups = [...new Set(options.map((option) => option.group))];
    return (
        <SelectPrimitive.Root
            dir={
                props.dir === "rtl" || props.dir === "ltr"
                    ? props.dir
                    : direction
            }
            value={value === undefined ? localValue : String(value)}
            onValueChange={(next) => {
                const selected = next === emptyValue ? "" : next;
                setLocalValue(selected);
                onValueChange?.(selected);
            }}
            disabled={disabled}
            required={required}
            name={name}
            form={form}
            autoComplete={autoComplete}
        >
            <div className={cn("min-w-0", containerClassName)}>
                <SelectPrimitive.Trigger
                    {...field}
                    id={id}
                    title={title}
                    onBlur={
                        onBlur as ComponentProps<
                            typeof SelectPrimitive.Trigger
                        >["onBlur"]
                    }
                    onFocus={
                        onFocus as ComponentProps<
                            typeof SelectPrimitive.Trigger
                        >["onFocus"]
                    }
                    tabIndex={tabIndex}
                    aria-label={ariaLabel}
                    aria-labelledby={ariaLabelledby}
                    aria-describedby={
                        ariaDescribedby ?? field["aria-describedby"]
                    }
                    aria-invalid={ariaInvalid ?? field["aria-invalid"]}
                    data-slot="select"
                    className={cn(
                        controlStyles,
                        "flex h-11 w-full items-center justify-between gap-2 px-3 text-left text-base sm:h-9 sm:text-sm [&>span:first-child]:truncate",
                        className,
                    )}
                >
                    <SelectPrimitive.Value placeholder={placeholder} />
                    <SelectPrimitive.Icon asChild>
                        <ChevronDown
                            aria-hidden="true"
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                    </SelectPrimitive.Icon>
                </SelectPrimitive.Trigger>
            </div>
            <SelectPrimitive.Portal>
                <SelectPrimitive.Content
                    position="popper"
                    sideOffset={4}
                    collisionPadding={8}
                    className="z-[130] max-h-[var(--radix-select-content-available-height)] min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
                >
                    <SelectPrimitive.ScrollUpButton className="flex justify-center py-1">
                        <ChevronUp className="size-4" />
                    </SelectPrimitive.ScrollUpButton>
                    <SelectPrimitive.Viewport className="max-h-80 p-1">
                        {groups.map((group) =>
                            group === undefined ? (
                                options
                                    .filter(
                                        (option) => option.group === undefined,
                                    )
                                    .map(renderOption)
                            ) : (
                                <SelectPrimitive.Group key={group}>
                                    <SelectPrimitive.Label className="px-2 py-2 text-xs font-semibold text-muted-foreground">
                                        {group}
                                    </SelectPrimitive.Label>
                                    {options
                                        .filter(
                                            (option) => option.group === group,
                                        )
                                        .map(renderOption)}
                                </SelectPrimitive.Group>
                            ),
                        )}
                    </SelectPrimitive.Viewport>
                    <SelectPrimitive.ScrollDownButton className="flex justify-center py-1">
                        <ChevronDown className="size-4" />
                    </SelectPrimitive.ScrollDownButton>
                </SelectPrimitive.Content>
            </SelectPrimitive.Portal>
        </SelectPrimitive.Root>
    );
}

function SearchableSelect(props: SelectProps) {
    const t = useAdminTranslation();
    const field = useFieldControl(props.id);
    const options = useMemo(
        () => selectOptions(props.children),
        [props.children],
    );
    const trigger = useRef<HTMLButtonElement>(null);
    const native = useRef<HTMLSelectElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [invalid, setInvalid] = useState(false);
    const errorId = useId();
    const [local, setLocal] = useState(() =>
        props.defaultValue === undefined
            ? props.multiple
                ? []
                : [options.find((option) => !option.disabled)?.value ?? ""]
            : Array.isArray(props.defaultValue)
              ? props.defaultValue.map(String)
              : [String(props.defaultValue)],
    );
    const selection =
        props.value === undefined
            ? local
            : Array.isArray(props.value)
              ? props.value.map(String)
              : [String(props.value)];
    const selected = options.filter((option) =>
        selection.includes(option.value),
    );
    const visible = options.filter((option) =>
        normalizeSearch(
            `${option.group ?? ""} ${optionText(option.label)}`,
        ).includes(normalizeSearch(query)),
    );
    useEffect(() => {
        const form = native.current?.form;
        const reset = () =>
            setLocal(
                props.defaultValue === undefined
                    ? props.multiple
                        ? []
                        : [
                              options.find((option) => !option.disabled)
                                  ?.value ?? "",
                          ]
                    : Array.isArray(props.defaultValue)
                      ? props.defaultValue.map(String)
                      : [String(props.defaultValue)],
            );
        form?.addEventListener("reset", reset);
        return () => form?.removeEventListener("reset", reset);
    }, [props.defaultValue, props.multiple, options]);
    const choose = (value: string) => {
        if (props.disabled) return;
        const next = props.multiple
            ? selection.includes(value)
                ? selection.filter((item) => item !== value)
                : [...selection, value]
            : [value];
        setInvalid(false);
        setLocal(next);
        if (props.multiple) props.onValuesChange?.(next);
        else props.onValueChange?.(value);
        if (!props.multiple) setOpen(false);
    };
    const {
        searchable,
        defaultValue,
        containerClassName,
        onValueChange,
        onValuesChange,
        ...nativeProps
    } = props;
    return (
        <div className={cn("min-w-0", containerClassName)}>
            <select
                {...nativeProps}
                {...field}
                id={undefined}
                ref={native}
                value={props.multiple ? selection : (selection[0] ?? "")}
                tabIndex={-1}
                aria-hidden="true"
                className="sr-only"
                onChange={(event) => {
                    const next = Array.from(
                        event.target.selectedOptions,
                        (option) => option.value,
                    );
                    setInvalid(false);
                    setLocal(next);
                    if (props.multiple) onValuesChange?.(next);
                    else onValueChange?.(event.target.value);
                }}
                onInvalid={(event) => {
                    event.preventDefault();
                    setInvalid(true);
                    trigger.current?.focus();
                }}
            />
            <button
                ref={trigger}
                type="button"
                {...field}
                id={props.id}
                disabled={props.disabled}
                title={props.title}
                tabIndex={props.tabIndex}
                aria-label={props["aria-label"]}
                aria-labelledby={props["aria-labelledby"]}
                aria-describedby={
                    [
                        props["aria-describedby"] ?? field["aria-describedby"],
                        invalid ? errorId : undefined,
                    ]
                        .filter(Boolean)
                        .join(" ") || undefined
                }
                aria-invalid={
                    invalid || (props["aria-invalid"] ?? field["aria-invalid"])
                }
                aria-required={props.required}
                aria-haspopup="dialog"
                aria-expanded={open}
                onFocus={props.onFocus as ComponentProps<"button">["onFocus"]}
                onBlur={props.onBlur as ComponentProps<"button">["onBlur"]}
                onClick={() => {
                    setQuery("");
                    setOpen(true);
                }}
                className={cn(
                    controlStyles,
                    "flex h-11 items-center justify-between gap-2 px-3 text-start text-base sm:h-9 sm:text-sm",
                    props.className,
                )}
            >
                <span className="truncate">
                    {selected.length
                        ? selected
                              .map((option) => optionText(option.label))
                              .join(", ")
                        : t("Select…")}
                </span>
                <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
            </button>
            {invalid && (
                <p
                    id={errorId}
                    role="alert"
                    className="mt-1 text-xs text-destructive"
                >
                    {t("Please select an option.")}
                </p>
            )}
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title={
                    props["aria-label"] ??
                    trigger.current?.labels?.[0]?.textContent ??
                    t("Select…")
                }
                size="sm"
                footer={
                    props.multiple && (
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm text-muted-foreground">
                                {t(":count selected", {
                                    count: selected.length,
                                })}
                            </span>
                            <Button onClick={() => setOpen(false)}>
                                {t("Done")}
                            </Button>
                        </div>
                    )
                }
            >
                <div className="relative mb-3">
                    <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        autoFocus
                        className={cn(
                            controlStyles,
                            "h-11 py-2 ps-9 pe-3 text-sm sm:h-9",
                        )}
                        value={query}
                        placeholder={t("Search…")}
                        aria-label={t("Search options")}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>
                <div
                    className="max-h-72 space-y-1 overflow-y-auto"
                    role="group"
                    aria-label={t("Options")}
                >
                    {visible.map((option, index) => (
                        <div key={option.value}>
                            {option.group &&
                                (index === 0 ||
                                    visible[index - 1].group !==
                                        option.group) && (
                                    <p className="px-3 py-2 text-xs font-semibold text-muted-foreground">
                                        {option.group}
                                    </p>
                                )}
                            <button
                                type="button"
                                disabled={props.disabled || option.disabled}
                                aria-pressed={selection.includes(option.value)}
                                className="flex min-h-10 w-full items-center gap-3 rounded-md px-3 py-2 text-start text-sm outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 aria-pressed:bg-primary/10"
                                onClick={() => choose(option.value)}
                            >
                                <span className="flex size-4 shrink-0 items-center justify-center">
                                    {selection.includes(option.value) && (
                                        <Check className="size-4" />
                                    )}
                                </span>
                                <span>{option.label}</span>
                            </button>
                        </div>
                    ))}
                    {!visible.length && (
                        <p
                            role="status"
                            className="p-6 text-center text-sm text-muted-foreground"
                        >
                            {t("No results found.")}
                        </p>
                    )}
                </div>
            </Dialog>
        </div>
    );
}
function optionText(label: React.ReactNode): string {
    if (typeof label === "string" || typeof label === "number")
        return String(label);
    if (Array.isArray(label)) return label.map(optionText).join("");
    return "";
}
export function Select(props: SelectProps) {
    const options = selectOptions(props.children);
    return props.multiple ||
        props.searchable ||
        (props.searchable !== false && options.length >= 10) ? (
        <SearchableSelect {...props} />
    ) : (
        <SingleSelect {...props} />
    );
}
