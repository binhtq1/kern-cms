import { useId, useRef, useState } from "react";
import { ChevronDown } from "lucide-react";
import { Input } from "./ui/input";
import { completeTag, parseTags, tagSuggestions } from "@admin/lib/tags";

export function TagsInput({
    id,
    label,
    value,
    options,
    onValueChange,
    disabled,
    readOnly,
    placeholder,
}: {
    id?: string;
    label: string;
    value: string;
    options: string[];
    onValueChange: (value: string) => void;
    disabled?: boolean;
    readOnly?: boolean;
    placeholder?: string;
}) {
    const listId = useId();
    const input = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const suggestions = tagSuggestions(value, options);
    const expanded = open && !disabled && !readOnly && suggestions.length > 0;
    const choose = (tag: string) => {
        if (disabled || readOnly) return;
        onValueChange(completeTag(value, tag));
        setActive(-1);
        setOpen(false);
        input.current?.focus();
    };
    return (
        <div className="relative min-w-0">
            <Input
                id={id}
                ref={input}
                value={value}
                disabled={disabled}
                readOnly={readOnly}
                placeholder={placeholder}
                role="combobox"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                aria-expanded={expanded}
                aria-controls={expanded ? listId : undefined}
                aria-activedescendant={
                    expanded && active >= 0 && active < suggestions.length
                        ? `${listId}-${active}`
                        : undefined
                }
                autoComplete="off"
                className="pr-9"
                onFocus={() => {
                    setOpen(true);
                    setActive(-1);
                }}
                onBlur={() => {
                    setOpen(false);
                    setActive(-1);
                    if (!disabled && !readOnly)
                        onValueChange(parseTags(value).join(", "));
                }}
                onChange={(event) => {
                    onValueChange(event.target.value);
                    setOpen(true);
                    setActive(-1);
                }}
                onKeyDown={(event) => {
                    if (disabled || readOnly || event.nativeEvent.isComposing)
                        return;
                    if (event.key === "Escape") {
                        if (open) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        setOpen(false);
                        setActive(-1);
                    } else if (
                        event.key === "ArrowDown" ||
                        event.key === "ArrowUp"
                    ) {
                        if (!suggestions.length) return;
                        event.preventDefault();
                        setOpen(true);
                        const next =
                            event.key === "ArrowDown"
                                ? (active + 1) % suggestions.length
                                : active <= 0
                                  ? suggestions.length - 1
                                  : active - 1;
                        setActive(next);
                        document
                            .getElementById(`${listId}-${next}`)
                            ?.scrollIntoView({ block: "nearest" });
                    } else if (event.key === "Enter") {
                        event.preventDefault();
                        if (expanded && active >= 0 && suggestions[active])
                            choose(suggestions[active]);
                        else {
                            const tag = value.split(",").pop()?.trim();
                            if (tag) choose(tag);
                        }
                    }
                }}
            />
            <ChevronDown
                aria-hidden="true"
                className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            {expanded && (
                <div
                    id={listId}
                    role="listbox"
                    aria-label={label}
                    className="absolute inset-x-0 top-full z-50 mt-1 max-h-60 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                >
                    {suggestions.map((tag, index) => (
                        <div
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={index === active}
                            key={tag}
                            className={`cursor-pointer rounded-sm px-3 py-2 text-sm ${index === active ? "bg-accent text-accent-foreground" : "hover:bg-accent hover:text-accent-foreground"}`}
                            onMouseEnter={() => setActive(index)}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => choose(tag)}
                        >
                            {tag}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
