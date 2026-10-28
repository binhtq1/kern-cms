import { CodeEditor } from "@admin/components/code-editor";
import { ColorPicker } from "@admin/components/color-picker";
import { Field } from "@admin/components/field";
import { FontPicker } from "@admin/components/font-picker";
import { IconPicker } from "@admin/components/icon-picker";
import { MediaPicker } from "@admin/components/media-picker";
import { SafeRichText } from "@admin/components/safe-rich-text";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Input, Textarea } from "@admin/components/ui/input";
import { Radio, RadioGroup } from "@admin/components/ui/radio";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import type { InertiaFormProps } from "@inertiajs/react";
import type { Dispatch, SetStateAction } from "react";
import { isSettingFieldVisible } from "../Settings/field-sections";
import type { SettingField, SettingProps, Value } from "../Settings/Form";
export function SettingFieldControl({
    field,
    form,
    kind,
    media,
    cleared,
    setCleared,
}: {
    field: SettingField;
    form: InertiaFormProps<Record<string, Value>>;
    kind: SettingProps["kind"];
    media: SettingProps["media"];
    cleared: string[];
    setCleared: Dispatch<SetStateAction<string[]>>;
}) {
    const t = useAdminTranslation();
    const change = (name: string, value: Value) => form.setData(name, value);
    const parse = (value: Value): Value => {
        if (typeof value === "string") {
            try {
                return JSON.parse(value);
            } catch {
                return value;
            }
        }
        return value;
    };

    if (!isSettingFieldVisible(field, form.data)) return null;
    if (field.type === "hidden")
        return (
            <input
                key={field.name}
                type="hidden"
                name={field.name}
                value={String(form.data[field.name] ?? "")}
            />
        );
    if (field.type === "notice")
        return (
            <div
                key={field.name}
                className="rounded-md border bg-muted/40 p-3 text-sm text-muted-foreground"
            >
                {field.description_html ? (
                    <SafeRichText html={field.description_html} />
                ) : (
                    field.description
                )}
            </div>
        );
    const value = form.data[field.name];
    const array = Array.isArray(value) ? value : [];
    return (
        <Field
            key={field.name}
            className={
                field.type === "checkbox"
                    ? "grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-4 gap-y-2 space-y-0 [&>label]:cursor-pointer [&>div]:col-span-2 [&>p]:col-span-2"
                    : undefined
            }
            label={
                kind === "media" &&
                /^media_sizes_.+_(width|height)$/.test(field.name)
                    ? t(
                          field.name.endsWith("_width")
                              ? "Width (px)"
                              : "Height (px)",
                      )
                    : field.label
            }
            description={
                field.description_html ? (
                    <SafeRichText html={field.description_html} />
                ) : (
                    field.description
                )
            }
            error={
                form.errors[field.name] ??
                Object.entries(form.errors).find(
                    ([key]) =>
                        key.startsWith(`${field.name}.`) ||
                        key.startsWith(`${field.name}_for_validation.`),
                )?.[1]
            }
            group={field.type === "radio"}
        >
            {(id) => {
                if (field.type === "code")
                    return (
                        <CodeEditor
                            id={id}
                            value={String(value ?? "")}
                            mode={field.mode}
                            disabled={field.disabled || field.readonly}
                            readOnly={field.readonly}
                            placeholder={field.placeholder}
                            onChange={(next) => change(field.name, next)}
                        />
                    );
                if (field.type === "font")
                    return (
                        <FontPicker
                            id={id}
                            value={String(value ?? "")}
                            choices={field.choices}
                            disabled={field.disabled || field.readonly}
                            readOnly={field.readonly}
                            placeholder={field.placeholder}
                            onChange={(next) => change(field.name, next)}
                        />
                    );
                if (field.type === "icon")
                    return (
                        <IconPicker
                            id={id}
                            value={String(value ?? "")}
                            iconsUrl={field.iconsUrl}
                            disabled={field.disabled || field.readonly}
                            readOnly={field.readonly}
                            placeholder={field.placeholder}
                            onChange={(next) => change(field.name, next)}
                        />
                    );
                if (field.type === "checkbox") {
                    const control = (
                        <Checkbox
                            id={id}
                            checked={[true, 1, "1"].includes(
                                value as string | number | boolean,
                            )}
                            disabled={field.disabled || field.readonly}
                            onCheckedChange={(checked) =>
                                change(field.name, checked === true ? 1 : 0)
                            }
                        />
                    );
                    return control;
                }
                if (field.type === "radio")
                    return (
                        <RadioGroup
                            className="flex flex-wrap gap-x-5 gap-y-2"
                            id={id}
                            name={field.name}
                            value={String(value ?? "")}
                            disabled={field.disabled || field.readonly}
                            onValueChange={(next) => change(field.name, next)}
                            aria-labelledby={`${id}-label`}
                        >
                            {field.choices.map((choice) =>
                                choice.image ? (
                                    <label
                                        key={choice.value}
                                        className={cn(
                                            "min-w-0 basis-52 flex-1 cursor-pointer",
                                            (field.disabled ||
                                                field.readonly) &&
                                                "cursor-not-allowed opacity-60",
                                        )}
                                    >
                                        <Card
                                            className={cn(
                                                "h-full overflow-hidden focus-within:ring-2 focus-within:ring-ring/25",
                                                String(value ?? "") ===
                                                    choice.value &&
                                                    "border-primary-ink bg-primary-ink/5",
                                            )}
                                        >
                                            <img
                                                src={choice.image}
                                                alt=""
                                                className="aspect-video w-full border-b bg-muted/30 object-contain p-3"
                                            />
                                            <CardContent className="flex items-center gap-2 p-3">
                                                <Radio value={choice.value} />
                                                <span className="text-sm">
                                                    {choice.label}
                                                </span>
                                            </CardContent>
                                        </Card>
                                    </label>
                                ) : (
                                    <label
                                        key={choice.value}
                                        className="flex items-center gap-2"
                                    >
                                        <Radio value={choice.value} />
                                        <span className="text-sm">
                                            {choice.label}
                                        </span>
                                    </label>
                                ),
                            )}
                        </RadioGroup>
                    );
                if (["select", "multiple"].includes(field.type))
                    return (
                        <Select
                            id={id}
                            disabled={field.disabled || field.readonly}
                            multiple={field.type === "multiple"}
                            value={
                                field.type === "multiple"
                                    ? array.map(String)
                                    : String(value ?? "")
                            }
                            onValueChange={(value) =>
                                change(
                                    field.name,
                                    field.type === "multiple" ? [value] : value,
                                )
                            }
                            onValuesChange={(values) =>
                                change(
                                    field.name,
                                    field.type === "multiple"
                                        ? values
                                        : (values[0] ?? ""),
                                )
                            }
                        >
                            {field.choices.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </Select>
                    );
                if (field.type === "color")
                    return (
                        <ColorPicker
                            id={id}
                            value={String(value ?? "")}
                            allowInherited={
                                field.name === "admin_heading_color"
                            }
                            disabled={field.disabled || field.readonly}
                            onChange={(color) => change(field.name, color)}
                        />
                    );
                if (field.type === "image")
                    return (
                        <MediaPicker
                            aspect={
                                /banner|cover/.test(field.name)
                                    ? "banner"
                                    : /logo|favicon/.test(field.name)
                                      ? "square"
                                      : "landscape"
                            }
                            listUrl={media?.list}
                            uploadUrl={media?.upload}
                            id={id}
                            value={String(value ?? "")}
                            onChange={(v) => change(field.name, v)}
                        />
                    );
                if (["emails", "images"].includes(field.type))
                    return (
                        <div className="space-y-2">
                            {array.map((item, index) => (
                                <div key={index} className="flex gap-2">
                                    {field.type === "images" ? (
                                        <MediaPicker
                                            listUrl={media?.list}
                                            uploadUrl={media?.upload}
                                            value={String(item)}
                                            onChange={(v) =>
                                                change(
                                                    field.name,
                                                    array.map((old, i) =>
                                                        i === index ? v : old,
                                                    ),
                                                )
                                            }
                                        />
                                    ) : (
                                        <Input
                                            id={index === 0 ? id : undefined}
                                            aria-label={`${field.label} ${index + 1}`}
                                            type="email"
                                            value={String(item)}
                                            onChange={(e) =>
                                                change(
                                                    field.name,
                                                    array.map((old, i) =>
                                                        i === index
                                                            ? e.target.value
                                                            : old,
                                                    ),
                                                )
                                            }
                                        />
                                    )}
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            change(
                                                field.name,
                                                array.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        {t("Remove")}
                                    </Button>
                                </div>
                            ))}
                            <Button
                                variant="outline"
                                onClick={() =>
                                    change(field.name, [...array, ""])
                                }
                                disabled={
                                    field.type === "emails" && array.length >= 4
                                }
                            >
                                {t("Add")}
                            </Button>
                        </div>
                    );
                if (field.type === "repeater")
                    return (
                        <div className="space-y-3">
                            {array.map((item, index) => {
                                const row = Array.isArray(item) ? item : [];
                                return (
                                    <div
                                        key={index}
                                        className="space-y-3 rounded-md border p-3"
                                    >
                                        {field.children?.map((child) => {
                                            const name = child.attributes.name;
                                            const record = row.find(
                                                (entry) =>
                                                    entry &&
                                                    typeof entry === "object" &&
                                                    !Array.isArray(entry) &&
                                                    entry.key === name,
                                            ) as
                                                | {
                                                      key: string;
                                                      value: Value;
                                                  }
                                                | undefined;
                                            return (
                                                <Field
                                                    key={name}
                                                    label={child.label}
                                                >
                                                    {(childId) => (
                                                        <Input
                                                            id={childId}
                                                            value={String(
                                                                record?.value ??
                                                                    "",
                                                            )}
                                                            onChange={(e) => {
                                                                const newRow =
                                                                    row.filter(
                                                                        (
                                                                            entry,
                                                                        ) =>
                                                                            !(
                                                                                entry &&
                                                                                typeof entry ===
                                                                                    "object" &&
                                                                                !Array.isArray(
                                                                                    entry,
                                                                                ) &&
                                                                                entry.key ===
                                                                                    name
                                                                            ),
                                                                    );
                                                                change(
                                                                    field.name,
                                                                    array.map(
                                                                        (
                                                                            old,
                                                                            i,
                                                                        ) =>
                                                                            i ===
                                                                            index
                                                                                ? [
                                                                                      ...newRow,
                                                                                      {
                                                                                          key: name,
                                                                                          value: e
                                                                                              .target
                                                                                              .value,
                                                                                      },
                                                                                  ]
                                                                                : old,
                                                                    ),
                                                                );
                                                            }}
                                                        />
                                                    )}
                                                </Field>
                                            );
                                        })}
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                change(
                                                    field.name,
                                                    array.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            {t("Remove")}
                                        </Button>
                                    </div>
                                );
                            })}
                            <Button
                                variant="outline"
                                onClick={() =>
                                    change(field.name, [...array, []])
                                }
                            >
                                {t("Add")}
                            </Button>
                        </div>
                    );
                if (field.type === "json-upload")
                    return (
                        <Input
                            id={id}
                            type="file"
                            accept="application/json,.json"
                            onChange={async (e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    try {
                                        const text = await file.text();
                                        JSON.parse(text);
                                        change(
                                            "analytics_service_account_credentials",
                                            text,
                                        );
                                        form.clearErrors(field.name);
                                    } catch {
                                        form.setError(
                                            field.name,
                                            t("Invalid JSON"),
                                        );
                                    }
                                }
                            }}
                        />
                    );
                if (field.type === "textarea")
                    return (
                        <Textarea
                            id={id}
                            className="min-h-40 font-mono"
                            value={String(value ?? "")}
                            disabled={field.disabled || field.readonly}
                            onChange={(e) => change(field.name, e.target.value)}
                        />
                    );
                if (field.type === "tags") {
                    const tags = parse(value);
                    const text = Array.isArray(tags)
                        ? tags
                              .map((item) =>
                                  typeof item === "object" &&
                                  item &&
                                  !Array.isArray(item)
                                      ? String(item.value ?? "")
                                      : String(item),
                              )
                              .join(", ")
                        : String(value ?? "");
                    return (
                        <Input
                            id={id}
                            value={text}
                            onChange={(e) =>
                                change(
                                    field.name,
                                    JSON.stringify(
                                        e.target.value
                                            .split(",")
                                            .map((v) => ({
                                                value: v.trim(),
                                            }))
                                            .filter((v) => v.value),
                                    ),
                                )
                            }
                        />
                    );
                }
                return (
                    <div className="space-y-2">
                        <Input
                            id={id}
                            type={field.type}
                            autoComplete={
                                field.secret ? "new-password" : undefined
                            }
                            placeholder={
                                field.stored
                                    ? t("Leave blank to keep the current value")
                                    : field.placeholder
                            }
                            value={String(value ?? "")}
                            disabled={field.disabled || field.readonly}
                            onChange={(e) => change(field.name, e.target.value)}
                        />
                        {field.secret && field.stored && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    change(field.name, "");
                                    setCleared(
                                        cleared.includes(field.name)
                                            ? cleared.filter(
                                                  (name) => name !== field.name,
                                              )
                                            : [...cleared, field.name],
                                    );
                                }}
                            >
                                {t(
                                    cleared.includes(field.name)
                                        ? "Keep saved value"
                                        : "Clear saved value",
                                )}
                            </Button>
                        )}
                    </div>
                );
            }}
        </Field>
    );
}
