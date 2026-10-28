import { CodeEditor } from "@admin/components/code-editor";
import { ColorPicker } from "@admin/components/color-picker";
import { Field } from "@admin/components/field";
import { FontPicker } from "@admin/components/font-picker";
import { IconPicker } from "@admin/components/icon-picker";
import {
    LanguageSwitcher,
    type LanguageChoice,
} from "@admin/components/language-switcher";
import { MediaPicker } from "@admin/components/media-picker";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { cn } from "@admin/lib/utils";
import { Link, useForm } from "@inertiajs/react";
import { useEffect, useRef } from "react";
type Row = { key: string; value: string }[];
type Value = string | number | boolean | string[] | Row[];
type Schema = {
    name: string;
    type: string;
    label: string;
    description: string;
    placeholder?: string;
    mode?: string;
    iconsUrl?: string;
    disabled?: boolean;
    readonly?: boolean;
    value: Value;
    choices: { value: string; label: string }[];
    children: Schema[];
};
type Section = { id: string; title: string; url: string; fields: Schema[] };
export default function Options({
    sections,
    current,
    submit,
    media,
    languages = [],
}: {
    sections: Section[];
    current: string;
    submit: string;
    media: { list: string; upload: string };
    languages?: LanguageChoice[];
}) {
    const t = useAdminTranslation();
    const section = sections.find((section) => section.id === current)!;
    const form = useForm<Record<string, Value>>(
        Object.fromEntries(
            section.fields.map((field) => [field.name, field.value]),
        ),
    );
    const activeSection = useRef(current);
    useEffect(() => {
        if (activeSection.current === current) return;
        const values = Object.fromEntries(
            section.fields.map((field) => [field.name, field.value]),
        );
        activeSection.current = current;
        form.setDefaults(values);
        form.setData(values);
    }, [current, section.fields, form]);
    useUnsavedChanges(form.isDirty, form.processing);
    function control(
        field: Schema,
        value: Value,
        onChange: (value: Value) => void,
        id: string,
        path = field.name,
    ) {
        const locked = Boolean(field.disabled || field.readonly);
        if (field.type === "code")
            return (
                <CodeEditor
                    id={id}
                    value={String(value ?? "")}
                    mode={field.mode}
                    disabled={field.disabled}
                    readOnly={field.readonly}
                    placeholder={field.placeholder}
                    onChange={onChange}
                />
            );
        if (field.type === "font")
            return (
                <FontPicker
                    id={id}
                    value={String(value ?? "")}
                    choices={field.choices}
                    disabled={field.disabled}
                    readOnly={field.readonly}
                    placeholder={field.placeholder}
                    onChange={onChange}
                />
            );
        if (field.type === "icon")
            return (
                <IconPicker
                    id={id}
                    value={String(value ?? "")}
                    iconsUrl={field.iconsUrl}
                    disabled={field.disabled}
                    readOnly={field.readonly}
                    placeholder={field.placeholder}
                    onChange={onChange}
                />
            );
        if (field.type === "hidden")
            return (
                <input
                    id={id}
                    type="hidden"
                    value={String(value ?? "")}
                    readOnly
                />
            );
        if (field.type === "textarea")
            return (
                <Textarea
                    id={id}
                    value={String(value ?? "")}
                    disabled={field.disabled}
                    readOnly={field.readonly}
                    placeholder={field.placeholder}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        if (field.type === "select")
            return (
                <Select
                    id={id}
                    disabled={locked}
                    value={String(value ?? "")}
                    onValueChange={(value) => onChange(value)}
                >
                    {field.choices.map((choice) => (
                        <option key={choice.value} value={choice.value}>
                            {choice.label}
                        </option>
                    ))}
                </Select>
            );
        if (field.type === "multiselect")
            return (
                <Select
                    id={id}
                    multiple
                    disabled={locked}
                    value={value as string[]}
                    onValueChange={(value) => onChange([value])}
                    onValuesChange={(values) => onChange(values)}
                >
                    {field.choices.map((choice) => (
                        <option key={choice.value} value={choice.value}>
                            {choice.label}
                        </option>
                    ))}
                </Select>
            );
        if (field.type === "checkbox")
            return (
                <Checkbox
                    id={id}
                    disabled={locked}
                    checked={[true, 1, "1"].includes(value as string)}
                    onCheckedChange={(checked) =>
                        onChange(checked === true ? "1" : "0")
                    }
                />
            );
        if (field.type === "color")
            return (
                <ColorPicker
                    id={id}
                    disabled={locked}
                    value={String(value ?? "")}
                    onChange={onChange}
                />
            );
        if (field.type === "image" || field.type === "file")
            return (
                <MediaPicker
                    id={id}
                    disabled={locked}
                    imagesOnly={field.type === "image"}
                    aspect={
                        /banner|cover/.test(field.name)
                            ? "banner"
                            : /logo|favicon/.test(field.name)
                              ? "square"
                              : "landscape"
                    }
                    value={String(value ?? "")}
                    onChange={onChange}
                    listUrl={media.list}
                    uploadUrl={media.upload}
                />
            );
        if (field.type === "repeater") {
            const rows = Array.isArray(value) ? (value as Row[]) : [];
            const fixedRows = field.children.some(
                (child) => child.disabled || child.readonly,
            );
            return (
                <fieldset
                    id={id}
                    disabled={locked}
                    className="min-w-0 space-y-3"
                >
                    {fixedRows && !locked && (
                        <p className="text-xs text-muted-foreground">
                            {t(
                                "Rows with locked fields cannot be moved or removed. You can edit the other fields or add a new row.",
                            )}
                        </p>
                    )}
                    {rows.map((row, index) => (
                        <fieldset
                            key={index}
                            className="min-w-0 space-y-4 rounded-md border p-4"
                        >
                            <legend className="px-1 text-xs text-muted-foreground">
                                {index + 1}
                            </legend>
                            {field.children.map((child, childIndex) =>
                                child.type === "hidden" ? (
                                    <input
                                        key={child.name}
                                        type="hidden"
                                        value={String(
                                            row[childIndex]?.value ?? "",
                                        )}
                                        readOnly
                                    />
                                ) : (
                                    <Field
                                        key={child.name}
                                        label={child.label}
                                        description={child.description}
                                        error={
                                            form.errors[
                                                `${path}.${index}.${childIndex}.value`
                                            ]
                                        }
                                    >
                                        {(childId) =>
                                            control(
                                                {
                                                    ...child,
                                                    disabled:
                                                        locked ||
                                                        child.disabled,
                                                },
                                                row[childIndex]?.value ?? "",
                                                (next) =>
                                                    onChange(
                                                        rows.map(
                                                            (existing, i) =>
                                                                i === index
                                                                    ? field.children.map(
                                                                          (
                                                                              schema,
                                                                              j,
                                                                          ) => ({
                                                                              key: schema.name,
                                                                              value: String(
                                                                                  j ===
                                                                                      childIndex
                                                                                      ? next
                                                                                      : (existing[
                                                                                            j
                                                                                        ]
                                                                                            ?.value ??
                                                                                            ""),
                                                                              ),
                                                                          }),
                                                                      )
                                                                    : existing,
                                                        ),
                                                    ),
                                                childId,
                                                `${path}.${index}.${childIndex}.value`,
                                            )
                                        }
                                    </Field>
                                ),
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        locked || fixedRows || index === 0
                                    }
                                    onClick={() => {
                                        const next = [...rows];
                                        [next[index - 1], next[index]] = [
                                            next[index],
                                            next[index - 1],
                                        ];
                                        onChange(next);
                                    }}
                                >
                                    {t("Move up")}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        locked ||
                                        fixedRows ||
                                        index === rows.length - 1
                                    }
                                    onClick={() => {
                                        const next = [...rows];
                                        [next[index], next[index + 1]] = [
                                            next[index + 1],
                                            next[index],
                                        ];
                                        onChange(next);
                                    }}
                                >
                                    {t("Move down")}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    disabled={locked || fixedRows}
                                    onClick={() =>
                                        onChange(
                                            rows.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    {t("Remove")}
                                </Button>
                            </div>
                        </fieldset>
                    ))}
                    <Button
                        variant="outline"
                        disabled={locked || rows.length >= 100}
                        onClick={() =>
                            onChange([
                                ...rows,
                                field.children.map((child) => ({
                                    key: child.name,
                                    value: String(child.value ?? ""),
                                })),
                            ])
                        }
                    >
                        {t("Add item")}
                    </Button>
                </fieldset>
            );
        }
        return (
            <Input
                id={id}
                type={field.type === "hidden" ? "text" : field.type}
                disabled={field.disabled}
                readOnly={field.readonly}
                placeholder={field.placeholder}
                value={String(value ?? "")}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }
    return (
        <AdminLayout title={t("Theme options")}>
            <div className="mb-5 flex justify-end">
                <LanguageSwitcher languages={languages} />
            </div>
            <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[14rem_minmax(0,1fr)]">
                <nav
                    aria-label={t("Theme options")}
                    className="flex flex-wrap gap-1 lg:sticky lg:top-5 lg:block"
                >
                    {sections.map((item) => (
                        <Link
                            key={item.id}
                            href={item.url}
                            aria-current={
                                item.id === current ? "page" : undefined
                            }
                            className={cn(
                                "block rounded-md px-3 py-2 text-sm hover:bg-muted",
                                item.id === current && "bg-accent font-medium",
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>
                <form
                    className="min-w-0 space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((values) =>
                            Object.fromEntries(
                                Object.entries(values).filter(([name]) => {
                                    const field = section.fields.find(
                                        (item) => item.name === name,
                                    );
                                    return !field?.disabled && !field?.readonly;
                                }),
                            ),
                        );
                        form.post(submit, {
                            preserveScroll: true,
                            onSuccess: () => form.setDefaults(form.data),
                        });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>{section.title}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5 p-5">
                            {form.errors.options && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {form.errors.options}
                                </p>
                            )}
                            {section.fields.map((field) =>
                                field.type === "hidden" ? (
                                    <input
                                        key={field.name}
                                        type="hidden"
                                        name={field.name}
                                        value={String(
                                            form.data[field.name] ?? "",
                                        )}
                                    />
                                ) : (
                                    <Field
                                        key={field.name}
                                        label={field.label}
                                        description={field.description}
                                        error={form.errors[field.name]}
                                    >
                                        {(id) =>
                                            control(
                                                field,
                                                form.data[field.name],
                                                (value) =>
                                                    form.setData(
                                                        field.name,
                                                        value,
                                                    ),
                                                id,
                                            )
                                        }
                                    </Field>
                                ),
                            )}
                        </CardContent>
                    </Card>
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">
                            {form.isDirty ? t("Unsaved changes") : ""}
                        </span>
                        <Button type="submit" loading={form.processing}>
                            {t("Save")}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
