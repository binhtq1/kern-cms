import { useRef, useState, type ComponentProps, type ChangeEvent } from "react";
import { usePage } from "@inertiajs/react";
import { CalendarDays, ChevronLeft, ChevronRight, Clock } from "lucide-react";
import { Dialog } from "./ui/dialog";
import { Button } from "./ui/button";
import { Select } from "./ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    calendarDays,
    localDateValue,
    withinBounds,
    timeFromDraft,
} from "@admin/lib/date-time";

export function DateTimePicker({
    className,
    ...props
}: ComponentProps<"input">) {
    const t = useAdminTranslation();
    const locale =
        (usePage().props.admin as { locale?: string } | undefined)?.locale ??
        "en";
    const input = useRef<HTMLInputElement>(null);
    const initialFocus = useRef<HTMLElement | null>(null);
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState("");
    const [view, setView] = useState(() => new Date());
    const type = props.type;
    const hasDate = type !== "time";
    const hasTime = type !== "date";
    const datePart = draft.split("T")[0];
    const timePart = timeFromDraft(draft, type);
    const days = calendarDays(view.getFullYear(), view.getMonth());
    const availableDays = days.filter(
        (date) =>
            date &&
            withinBounds(
                date,
                String(props.min ?? "").slice(0, 10),
                String(props.max ?? "").slice(0, 10),
            ),
    );
    const focusDate = availableDays.includes(datePart)
        ? datePart
        : availableDays[0];
    const changeDate = (next: string) =>
        setDraft(type === "datetime-local" ? `${next}T${timePart}` : next);
    const changeTime = (next: string) =>
        setDraft(
            type === "datetime-local"
                ? `${datePart || localDateValue(new Date())}T${next}`
                : next,
        );
    const apply = (next: string) => {
        if (!input.current || props.disabled || props.readOnly) return;
        input.current.value = next;
        const nativeEvent = new Event("change", { bubbles: true });
        props.onChange?.({
            target: input.current,
            currentTarget: input.current,
            nativeEvent,
            type: "change",
            bubbles: true,
            cancelable: false,
            defaultPrevented: false,
            eventPhase: 3,
            isTrusted: false,
            timeStamp: nativeEvent.timeStamp,
            preventDefault: () => nativeEvent.preventDefault(),
            stopPropagation: () => nativeEvent.stopPropagation(),
            isDefaultPrevented: () => false,
            isPropagationStopped: () => false,
            persist: () => {},
        } as ChangeEvent<HTMLInputElement>);
        setOpen(false);
    };
    const weekdays = Array.from({ length: 7 }, (_, day) =>
        new Intl.DateTimeFormat(locale, { weekday: "short" }).format(
            new Date(2024, 0, 7 + day),
        ),
    );
    const validator = input.current?.cloneNode() as
        HTMLInputElement | undefined;
    if (validator) validator.value = draft;
    const valid =
        !draft ||
        !validator ||
        (validator.value !== "" && validator.checkValidity());
    return (
        <div className="relative min-w-0">
            <input
                {...props}
                ref={(node) => {
                    input.current = node;
                    if (typeof props.ref === "function") props.ref(node);
                    else if (props.ref) props.ref.current = node;
                }}
                className={`${className ?? ""} pe-11 [&::-webkit-calendar-picker-indicator]:hidden`}
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="absolute end-0.5 top-1/2 -translate-y-1/2"
                disabled={props.disabled || props.readOnly}
                aria-label={t(hasDate ? "Choose date" : "Choose time")}
                onClick={() => {
                    const value = input.current?.value ?? "";
                    setDraft(value);
                    const [year, month, day] = value
                        .split("T")[0]
                        .split("-")
                        .map(Number);
                    setView(
                        year && month && day
                            ? new Date(year, month - 1, day)
                            : new Date(),
                    );
                    setOpen(true);
                }}
            >
                {hasDate ? (
                    <CalendarDays className="size-4" />
                ) : (
                    <Clock className="size-4" />
                )}
            </Button>
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title={t(hasDate ? "Choose date" : "Choose time")}
                size="sm"
                initialFocusRef={initialFocus}
                footer={
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            disabled={
                                props.required ||
                                props.disabled ||
                                props.readOnly
                            }
                            onClick={() => apply("")}
                        >
                            {t("Clear")}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            disabled={
                                !valid ||
                                !draft ||
                                props.disabled ||
                                props.readOnly
                            }
                            onClick={() => apply(draft)}
                        >
                            {t("Apply")}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-4">
                    {hasDate && (
                        <>
                            <div className="flex items-center justify-between gap-2">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t("Previous month")}
                                    onClick={() =>
                                        setView(
                                            new Date(
                                                view.getFullYear(),
                                                view.getMonth() - 1,
                                                1,
                                            ),
                                        )
                                    }
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <span
                                    className="font-medium"
                                    aria-live="polite"
                                >
                                    {new Intl.DateTimeFormat(locale, {
                                        month: "long",
                                        year: "numeric",
                                    }).format(view)}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t("Next month")}
                                    onClick={() =>
                                        setView(
                                            new Date(
                                                view.getFullYear(),
                                                view.getMonth() + 1,
                                                1,
                                            ),
                                        )
                                    }
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                            <div
                                className="grid grid-cols-7 gap-1"
                                role="group"
                                aria-label={t("Choose date")}
                            >
                                {weekdays.map((day, index) => (
                                    <span
                                        key={index}
                                        className="py-2 text-center text-xs text-muted-foreground"
                                    >
                                        {day}
                                    </span>
                                ))}
                                {calendarDays(
                                    view.getFullYear(),
                                    view.getMonth(),
                                ).map((date, index) =>
                                    date ? (
                                        <Button
                                            key={date}
                                            variant={
                                                datePart === date
                                                    ? "default"
                                                    : "ghost"
                                            }
                                            className="px-0"
                                            aria-label={new Intl.DateTimeFormat(
                                                locale,
                                                { dateStyle: "long" },
                                            ).format(
                                                new Date(`${date}T12:00:00`),
                                            )}
                                            ref={
                                                date === focusDate
                                                    ? (node) => {
                                                          initialFocus.current =
                                                              node;
                                                      }
                                                    : undefined
                                            }
                                            aria-pressed={datePart === date}
                                            disabled={
                                                !withinBounds(
                                                    date,
                                                    String(
                                                        props.min ?? "",
                                                    ).slice(0, 10),
                                                    String(
                                                        props.max ?? "",
                                                    ).slice(0, 10),
                                                )
                                            }
                                            onClick={() => changeDate(date)}
                                        >
                                            {Number(date.slice(-2))}
                                        </Button>
                                    ) : (
                                        <span key={index} />
                                    ),
                                )}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    !withinBounds(
                                        localDateValue(new Date()),
                                        String(props.min ?? "").slice(0, 10),
                                        String(props.max ?? "").slice(0, 10),
                                    )
                                }
                                onClick={() => {
                                    const today = new Date();
                                    setView(today);
                                    changeDate(localDateValue(today));
                                }}
                            >
                                {t("Today")}
                            </Button>
                        </>
                    )}
                    {hasTime && (
                        <div
                            className="grid grid-cols-2 gap-3"
                            ref={(node) => {
                                if (!hasDate)
                                    initialFocus.current =
                                        node?.querySelector("button") ?? null;
                            }}
                        >
                            <label className="space-y-2 text-sm font-medium">
                                {t("Hour")}
                                <Select
                                    searchable={false}
                                    value={timePart.slice(0, 2)}
                                    onValueChange={(value) =>
                                        changeTime(
                                            `${value}:${timePart.slice(3) || "00"}`,
                                        )
                                    }
                                >
                                    {Array.from({ length: 24 }, (_, n) => (
                                        <option
                                            key={n}
                                            value={String(n).padStart(2, "0")}
                                        >
                                            {String(n).padStart(2, "0")}
                                        </option>
                                    ))}
                                </Select>
                            </label>
                            <label className="space-y-2 text-sm font-medium">
                                {t("Minute")}
                                <Select
                                    searchable={false}
                                    value={timePart.slice(3, 5)}
                                    onValueChange={(value) =>
                                        changeTime(
                                            `${timePart.slice(0, 2)}:${value}${timePart.length > 5 ? timePart.slice(5) : ""}`,
                                        )
                                    }
                                >
                                    {Array.from({ length: 60 }, (_, n) => (
                                        <option
                                            key={n}
                                            value={String(n).padStart(2, "0")}
                                        >
                                            {String(n).padStart(2, "0")}
                                        </option>
                                    ))}
                                </Select>
                            </label>
                        </div>
                    )}
                    {!valid && (
                        <p role="alert" className="text-sm text-destructive">
                            {t("Choose a value within the allowed range.")}
                        </p>
                    )}
                </div>
            </Dialog>
        </div>
    );
}
