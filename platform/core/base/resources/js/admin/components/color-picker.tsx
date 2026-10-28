import { useId, useState } from "react";
import { Palette, RotateCcw } from "lucide-react";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    parseColor,
    formatColor,
    normalizeColorValue,
    colorPalette,
    type Color,
} from "@admin/lib/color";
import { useFieldControl } from "./field";

const checkerboard = {
    backgroundImage:
        "conic-gradient(#9993 25%, transparent 0 50%, #9993 0 75%, transparent 0)",
    backgroundSize: "12px 12px",
};
export function ColorPicker({
    id,
    value,
    onChange,
    disabled = false,
    readOnly = false,
    compact = false,
    label,
    allowInherited = false,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    readOnly?: boolean;
    compact?: boolean;
    label?: string;
    allowInherited?: boolean;
}) {
    const t = useAdminTranslation();
    const seed = useId();
    const inputId = id ?? seed;
    const field = useFieldControl(inputId);
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value);
    const parsed = parseColor(draft);
    const normalizedDraft = normalizeColorValue(draft, allowInherited);
    const normalizedValue = normalizeColorValue(value, allowInherited);
    const invalid = normalizedDraft === null;
    const update = (channel: keyof Color, next: number) =>
        setDraft(
            formatColor({
                ...(parsed ?? { r: 0, g: 0, b: 0, a: 1 }),
                [channel]: next,
            }),
        );
    const locked = disabled || readOnly;
    return (
        <div className={compact ? "inline-flex" : "max-w-sm"}>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    disabled={locked}
                    aria-label={label ?? t("Choose color")}
                    title={label ?? t("Choose color")}
                    onClick={() => {
                        setDraft(value);
                        setOpen(true);
                    }}
                    className="relative shrink-0 overflow-hidden"
                >
                    <span
                        className="absolute inset-1 rounded-sm"
                        style={checkerboard}
                    >
                        <span
                            className="block size-full rounded-sm"
                            style={{
                                backgroundColor: parseColor(value)
                                    ? value
                                    : "transparent",
                            }}
                        />
                    </span>
                    {(!value || normalizedValue === "inherit") && (
                        <Palette className="relative size-4" />
                    )}
                </Button>
                {!compact && (
                    <Input
                        {...field}
                        id={inputId}
                        value={value}
                        disabled={disabled}
                        readOnly={readOnly}
                        placeholder="#000000"
                        spellCheck={false}
                        aria-label={id ? undefined : t("Color value")}
                        aria-invalid={
                            field["aria-invalid"] || normalizedValue === null
                        }
                        onChange={(event) => onChange(event.target.value)}
                        onBlur={() => {
                            if (
                                normalizedValue !== null &&
                                normalizedValue !== value
                            )
                                onChange(normalizedValue);
                        }}
                        className="font-mono"
                    />
                )}
                {!compact && (
                    <Button
                        variant="ghost"
                        size="icon"
                        disabled={locked || !value}
                        aria-label={t("Clear color")}
                        onClick={() => onChange("")}
                    >
                        <RotateCcw className="size-4" />
                    </Button>
                )}
            </div>
            {!compact && normalizedValue === null && (
                <p role="alert" className="mt-1 text-xs text-destructive">
                    {t("Use HEX, RGB or RGBA.")}
                </p>
            )}
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title={label ?? t("Choose color")}
                size="sm"
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="ghost" onClick={() => setDraft("")}>
                            {t("Clear color")}
                        </Button>
                        {allowInherited && (
                            <Button
                                variant="ghost"
                                onClick={() => setDraft("inherit")}
                            >
                                {t("Default")}
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            disabled={invalid || locked}
                            onClick={() => {
                                if (normalizedDraft === null) return;
                                onChange(normalizedDraft);
                                setOpen(false);
                            }}
                        >
                            {t("Apply")}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-5">
                    <div
                        className="h-16 overflow-hidden rounded-md border"
                        style={checkerboard}
                    >
                        <div
                            className="size-full"
                            style={{
                                backgroundColor: parsed ? draft : "transparent",
                            }}
                        />
                    </div>
                    <div
                        className="grid grid-cols-5 gap-1.5 sm:grid-cols-8"
                        aria-label={t("Color palette")}
                    >
                        {colorPalette.map((color) => (
                            <button
                                key={color}
                                type="button"
                                title={color}
                                aria-label={color}
                                aria-pressed={
                                    parsed
                                        ? formatColor(parsed) === color
                                        : false
                                }
                                className="size-11 rounded-md border border-black/15 sm:size-8 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 aria-pressed:ring-2 aria-pressed:ring-ring aria-pressed:ring-offset-2"
                                style={{ backgroundColor: color }}
                                onClick={() =>
                                    setDraft(
                                        formatColor({
                                            ...parseColor(color)!,
                                            a: parsed?.a ?? 1,
                                        }),
                                    )
                                }
                            />
                        ))}
                    </div>
                    <label className="block space-y-2 text-sm font-medium">
                        {t("Color value")}
                        <Input
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            spellCheck={false}
                            aria-invalid={invalid}
                            className="font-mono"
                        />
                    </label>
                    {invalid && (
                        <p role="alert" className="text-sm text-destructive">
                            {t("Use HEX, RGB or RGBA.")}
                        </p>
                    )}
                    <div className="space-y-3">
                        {(["r", "g", "b", "a"] as const).map((channel) => (
                            <label
                                key={channel}
                                className="grid grid-cols-[4.5rem_1fr_3rem] items-center gap-3 text-sm"
                            >
                                <span>
                                    {channel === "a"
                                        ? t("Opacity")
                                        : channel.toUpperCase()}
                                </span>
                                <input
                                    type="range"
                                    min="0"
                                    max={channel === "a" ? 100 : 255}
                                    value={
                                        channel === "a"
                                            ? Math.round((parsed?.a ?? 1) * 100)
                                            : (parsed?.[channel] ?? 0)
                                    }
                                    onChange={(event) =>
                                        update(
                                            channel,
                                            Number(event.target.value) /
                                                (channel === "a" ? 100 : 1),
                                        )
                                    }
                                    className="w-full accent-primary"
                                />
                                <span className="text-end tabular-nums text-muted-foreground">
                                    {channel === "a"
                                        ? `${Math.round((parsed?.a ?? 1) * 100)}%`
                                        : (parsed?.[channel] ?? 0)}
                                </span>
                            </label>
                        ))}
                    </div>
                </div>
            </Dialog>
        </div>
    );
}
