import { Field } from "@admin/components/field";
import { Button } from "@admin/components/ui/button";
import { CardDescription } from "@admin/components/ui/card";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { Plus } from "lucide-react";
import type {
    Definition,
    Item,
    LinkData,
    Value,
    WidgetField,
} from "../lib/widget-types";
export function WidgetFieldControl({
    field,
    item,
    definition,
    change,
}: {
    field: WidgetField;
    item: Item;
    definition: Definition;
    change: (key: string, name: string, value: Value) => void;
}) {
    const t = useAdminTranslation();

    const value = item.data[field.name] ?? definition.defaults[field.name];
    if (field.type === "info")
        return <CardDescription key={field.name}>{field.text}</CardDescription>;
    if (field.type === "links") {
        const links: LinkData[] = Array.isArray(value)
            ? (value as LinkData[])
            : [];
        function setLink(row: number, name: string, value: string) {
            change(
                item.key,
                field.name,
                links.map((link, position) =>
                    position === row
                        ? [
                              ...link.filter((entry) => entry.key !== name),
                              {
                                  key: name,
                                  value,
                              },
                          ]
                        : link,
                ),
            );
        }
        return (
            <div key={field.name} className="space-y-3">
                <h4 className="min-w-0 flex-1 text-sm font-medium">
                    {field.label}
                </h4>
                {links.map((link, row) => {
                    const values = Object.fromEntries(
                        link.map((entry) => [entry.key, entry.value]),
                    );
                    return (
                        <div
                            key={row}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                <Field label={t("Label")}>
                                    {(id) => (
                                        <Input
                                            id={id}
                                            value={values.label ?? ""}
                                            onChange={(event) =>
                                                setLink(
                                                    row,
                                                    "label",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                                <Field label={t("URL")}>
                                    {(id) => (
                                        <Input
                                            id={id}
                                            value={values.url ?? ""}
                                            onChange={(event) =>
                                                setLink(
                                                    row,
                                                    "url",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>
                            <Field label={t("Attributes")}>
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={values.attributes ?? ""}
                                        onChange={(event) =>
                                            setLink(
                                                row,
                                                "attributes",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={[
                                            "1",
                                            "on",
                                            "yes",
                                            "true",
                                        ].includes(
                                            String(values.is_open_new_tab),
                                        )}
                                        onCheckedChange={(checked) =>
                                            setLink(
                                                row,
                                                "is_open_new_tab",
                                                checked === true ? "1" : "0",
                                            )
                                        }
                                    />
                                    {t("Open in new tab")}
                                </label>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        change(
                                            item.key,
                                            field.name,
                                            links.filter(
                                                (_, position) =>
                                                    position !== row,
                                            ),
                                        )
                                    }
                                >
                                    {t("Remove link")}
                                </Button>
                            </div>
                        </div>
                    );
                })}
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        change(item.key, field.name, [
                            ...links,
                            [
                                {
                                    key: "label",
                                    value: "",
                                },
                                {
                                    key: "url",
                                    value: "/",
                                },
                                {
                                    key: "attributes",
                                    value: "",
                                },
                                {
                                    key: "is_open_new_tab",
                                    value: "0",
                                },
                            ],
                        ])
                    }
                >
                    <Plus />
                    {t("Add link")}
                </Button>
            </div>
        );
    }
    return (
        <Field key={field.name} label={field.label}>
            {(id) =>
                field.type === "select" ? (
                    <Select
                        id={id}
                        multiple={field.multiple}
                        value={
                            field.multiple
                                ? Array.isArray(value)
                                    ? value.map(String)
                                    : []
                                : String(value ?? "")
                        }
                        onValueChange={(value) =>
                            change(
                                item.key,
                                field.name,
                                field.multiple ? [value] : value,
                            )
                        }
                        onValuesChange={(values) =>
                            change(
                                item.key,
                                field.name,
                                field.multiple ? values : (values[0] ?? ""),
                            )
                        }
                    >
                        {field.choices.map((choice) => (
                            <option key={choice.value} value={choice.value}>
                                {choice.label}
                            </option>
                        ))}
                    </Select>
                ) : field.type === "textarea" ? (
                    <Textarea
                        id={id}
                        value={String(value ?? "")}
                        onChange={(event) =>
                            change(item.key, field.name, event.target.value)
                        }
                    />
                ) : (
                    <Input
                        id={id}
                        type={field.type === "number" ? "number" : "text"}
                        min={field.type === "number" ? 0 : undefined}
                        max={field.type === "number" ? 100 : undefined}
                        value={String(value ?? "")}
                        onChange={(event) =>
                            change(item.key, field.name, event.target.value)
                        }
                    />
                )
            }
        </Field>
    );
}
