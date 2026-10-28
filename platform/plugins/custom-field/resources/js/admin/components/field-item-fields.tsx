import { Field } from "@admin/components/field";
import { CardContent } from "@admin/components/ui/card";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { normalizeFieldSlug } from "../FieldGroups/field-group-state";

import type { ReactNode } from "react";
import type { FieldItem } from "../FieldGroups/field-group-state";
import { typeLabels } from "../lib/field-group-controls";
export function FieldItemFields({
    scope,
    isCollapsed,
    item,
    change,
    index,
    onRemove,
    types,
    fieldKey,
    children,
}: {
    scope: string;
    isCollapsed: boolean;
    item: FieldItem;
    change: (index: number, patch: Partial<FieldItem>) => void;
    index: number;
    onRemove: (item: FieldItem) => void;
    types: string[];
    fieldKey: string;
    children: ReactNode;
}) {
    const t = useAdminTranslation();
    return (
        <CardContent
            id={`${scope}-${fieldKey}`}
            hidden={isCollapsed}
            className="space-y-4"
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label={t("Title")} required>
                    {(id) => (
                        <Input
                            id={id}
                            value={item.title}
                            onBlur={() => {
                                if (!item.slug)
                                    change(index, {
                                        slug: normalizeFieldSlug(item.title),
                                    });
                            }}
                            onChange={(e) =>
                                change(index, {
                                    title: e.target.value,
                                })
                            }
                            required
                        />
                    )}
                </Field>
                <Field label={t("Slug")}>
                    {(id) => (
                        <Input
                            id={id}
                            value={item.slug}
                            onBlur={() =>
                                change(index, {
                                    slug: normalizeFieldSlug(item.slug),
                                })
                            }
                            onChange={(e) =>
                                change(index, {
                                    slug: e.target.value,
                                })
                            }
                        />
                    )}
                </Field>
                <Field label={t("Type")}>
                    {(id) => (
                        <Select
                            id={id}
                            value={item.type}
                            onValueChange={(value) => {
                                if (item.items.length && value !== "repeater") {
                                    item.items.forEach(onRemove);
                                }
                                change(index, {
                                    type: value,
                                    items:
                                        value === "repeater" ? item.items : [],
                                });
                            }}
                        >
                            {types.map((type) => (
                                <option key={type} value={type}>
                                    {t(typeLabels[type] ?? type)}
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
                <Field label={t("Instructions")}>
                    {(id) => (
                        <Textarea
                            id={id}
                            value={item.instructions ?? ""}
                            onChange={(e) =>
                                change(index, {
                                    instructions: e.target.value,
                                })
                            }
                        />
                    )}
                </Field>
            </div>
            {["select", "radio", "checkbox"].includes(item.type) && (
                <Field label={t("Choices: value:label, one per line")}>
                    {(id) => (
                        <Textarea
                            id={id}
                            value={String(item.options.selectChoices ?? "")}
                            onChange={(e) =>
                                change(index, {
                                    options: {
                                        ...item.options,
                                        selectChoices: e.target.value,
                                    },
                                })
                            }
                        />
                    )}
                </Field>
            )}
            {(["text", "email", "password", "number"].includes(item.type)
                ? ["placeholderText", "defaultValue"]
                : item.type === "textarea"
                  ? ["placeholderText"]
                  : item.type === "select"
                    ? ["defaultValue"]
                    : item.type === "repeater"
                      ? ["buttonLabel"]
                      : []
            ).map((key) => (
                <Field
                    key={key}
                    label={t(
                        key === "placeholderText"
                            ? "Placeholder"
                            : key === "buttonLabel"
                              ? "Button label"
                              : "Default value",
                    )}
                >
                    {(id) => (
                        <Input
                            id={id}
                            value={String(item.options[key] ?? "")}
                            onChange={(e) =>
                                change(index, {
                                    options: {
                                        ...item.options,
                                        [key]: e.target.value,
                                    },
                                })
                            }
                        />
                    )}
                </Field>
            ))}
            {["textarea", "wysiwyg"].includes(item.type) && (
                <Field label={t("Default content")}>
                    {(id) => (
                        <Textarea
                            id={id}
                            value={String(
                                item.options.defaultValueTextarea ?? "",
                            )}
                            onChange={(e) =>
                                change(index, {
                                    options: {
                                        ...item.options,
                                        defaultValueTextarea: e.target.value,
                                    },
                                })
                            }
                        />
                    )}
                </Field>
            )}
            {item.type === "textarea" && (
                <Field label={t("Rows")}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            min={1}
                            max={10}
                            value={Number(item.options.rows ?? 4)}
                            onChange={(event) =>
                                change(index, {
                                    options: {
                                        ...item.options,
                                        rows: Number(event.target.value),
                                    },
                                })
                            }
                        />
                    )}
                </Field>
            )}
            {children}
        </CardContent>
    );
}
