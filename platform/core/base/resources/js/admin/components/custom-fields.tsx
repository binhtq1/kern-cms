import { ColorPicker } from "./color-picker";
import { Card } from "@admin/components/ui/card";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { Field } from "./field";
import { Input, Textarea } from "./ui/input";
import { Button } from "./ui/button";
import { Checkbox } from "./ui/checkbox";
import { Radio, RadioGroup } from "./ui/radio";
import { MediaPicker } from "./media-picker";
import { RichEditor } from "./rich-editor";
import type { CustomField, CustomFieldGroup } from "@admin/lib/custom-fields";
export { initializeCustomFields } from "@admin/lib/custom-fields";
export type { CustomField, CustomFieldGroup } from "@admin/lib/custom-fields";
function choices(value = "") {
    return value
        .split("\n")
        .filter((line) => line.trim())
        .map((line) => {
            const [key, ...label] = line.split(":");
            return {
                value: key.trim(),
                label: label.length ? label.join(":").trim() : key.trim(),
            };
        });
}
function Fields({
    fields,
    onChange,
    mediaUrl,
    uploadUrl,
}: {
    fields: CustomField[];
    onChange: (fields: CustomField[]) => void;
    mediaUrl?: string;
    uploadUrl?: string;
}) {
    const t = useAdminTranslation();
    function update(index: number, value: CustomField["value"]) {
        onChange(
            fields.map((item, i) => (i === index ? { ...item, value } : item)),
        );
    }
    return (
        <div className="space-y-5">
            {fields.map((field, index) => (
                <Field
                    key={`${field.id}-${index}`}
                    label={field.title}
                    group={["radio", "checkbox"].includes(field.type)}
                    description={field.instructions?.replace(/<[^>]*>/g, "")}
                >
                    {(id) => {
                        const value =
                            field.value ?? field.options?.defaultValue ?? "";
                        const text = typeof value === "string" ? value : "";
                        const options = choices(field.options?.selectChoices);
                        let input;
                        if (field.type === "repeater") {
                            const rows = (
                                Array.isArray(value) ? value : []
                            ) as CustomField[][];
                            input = (
                                <div className="space-y-3">
                                    {rows.map((row, rowIndex) => (
                                        <div
                                            key={rowIndex}
                                            className="space-y-3 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs text-muted-foreground">
                                                    {t("Item")}
                                                    {rowIndex + 1}
                                                </span>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            rowIndex === 0
                                                        }
                                                        onClick={() => {
                                                            const next = [
                                                                ...rows,
                                                            ];
                                                            [
                                                                next[
                                                                    rowIndex - 1
                                                                ],
                                                                next[rowIndex],
                                                            ] = [
                                                                next[rowIndex],
                                                                next[
                                                                    rowIndex - 1
                                                                ],
                                                            ];
                                                            update(index, next);
                                                        }}
                                                    >
                                                        {t("Move up")}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            update(
                                                                index,
                                                                rows.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        rowIndex,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {t("Remove")}
                                                    </Button>
                                                </div>
                                            </div>
                                            <Fields
                                                fields={row}
                                                onChange={(next) =>
                                                    update(
                                                        index,
                                                        rows.map((item, i) =>
                                                            i === rowIndex
                                                                ? next
                                                                : item,
                                                        ),
                                                    )
                                                }
                                                mediaUrl={mediaUrl}
                                                uploadUrl={uploadUrl}
                                            />
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            update(index, [
                                                ...rows,
                                                structuredClone(
                                                    field.items ?? [],
                                                ),
                                            ])
                                        }
                                    >
                                        {field.options?.buttonLabel ||
                                            t("Add item")}
                                    </Button>
                                </div>
                            );
                        } else if (field.type === "textarea") {
                            input = (
                                <Textarea
                                    id={id}
                                    value={text}
                                    rows={field.options?.rows ?? 3}
                                    placeholder={field.options?.placeholderText}
                                    onChange={(event) =>
                                        update(index, event.target.value)
                                    }
                                />
                            );
                        } else if (field.type === "wysiwyg") {
                            input = (
                                <RichEditor
                                    id={id}
                                    value={text}
                                    onChange={(next) => update(index, next)}
                                    mediaUrl={mediaUrl}
                                    uploadUrl={uploadUrl}
                                />
                            );
                        } else if (
                            field.type === "image" ||
                            field.type === "file"
                        ) {
                            input = (
                                <MediaPicker
                                    id={id}
                                    value={text}
                                    onChange={(next) => update(index, next)}
                                    listUrl={mediaUrl}
                                    uploadUrl={uploadUrl}
                                    imagesOnly={field.type === "image"}
                                />
                            );
                        } else if (field.type === "select") {
                            input = (
                                <Select
                                    id={id}

                                    value={text}
                                    onValueChange={(value) =>
                                        update(index, value)
                                    }
                                >
                                    {!options.some(
                                        (option) => option.value === "",
                                    ) && (
                                        <option value="">{t("Select…")}</option>
                                    )}
                                    {options.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </Select>
                            );
                        } else if (
                            field.type === "radio" ||
                            field.type === "checkbox"
                        ) {
                            const optionItems = options.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm has-[[data-state=checked]]:bg-primary/5 has-disabled:cursor-not-allowed hover:bg-muted/60"
                                >
                                    {field.type === "radio" ? (
                                        <Radio value={option.value} />
                                    ) : (
                                        <Checkbox
                                            checked={
                                                Array.isArray(value) &&
                                                value.includes(
                                                    option.value as never,
                                                )
                                            }
                                            onCheckedChange={(checked) => {
                                                const current = Array.isArray(
                                                    value,
                                                )
                                                    ? (value as string[])
                                                    : [];
                                                update(
                                                    index,
                                                    checked === true
                                                        ? [
                                                              ...current,
                                                              option.value,
                                                          ]
                                                        : current.filter(
                                                              (item) =>
                                                                  item !==
                                                                  option.value,
                                                          ),
                                                );
                                            }}
                                        />
                                    )}
                                    {option.label}
                                </label>
                            ));
                            input =
                                field.type === "radio" ? (
                                    <RadioGroup
                                        id={id}
                                        name={id}
                                        value={text}
                                        onValueChange={(next) =>
                                            update(index, next)
                                        }
                                        aria-labelledby={`${id}-label`}
                                        className="space-y-1"
                                    >
                                        {optionItems}
                                    </RadioGroup>
                                ) : (
                                    <div
                                        id={id}
                                        role="group"
                                        aria-labelledby={`${id}-label`}
                                        className="space-y-1"
                                    >
                                        {optionItems}
                                    </div>
                                );
                        } else if (field.type === "color") {
                            input = (
                                <ColorPicker
                                    id={id}
                                    value={text}
                                    onChange={(next) => update(index, next)}
                                />
                            );
                        } else {
                            input = (
                                <Input
                                    id={id}
                                    type={
                                        [
                                            "number",
                                            "email",
                                            "password",
                                            "date",
                                            "time",
                                            "color",
                                        ].includes(field.type)
                                            ? field.type
                                            : "text"
                                    }
                                    value={text}
                                    placeholder={field.options?.placeholderText}
                                    onChange={(event) =>
                                        update(index, event.target.value)
                                    }
                                />
                            );
                        }
                        return input;
                    }}
                </Field>
            ))}
        </div>
    );
}
export function CustomFields({
    groups,
    onChange,
    mediaUrl,
    uploadUrl,
}: {
    groups: CustomFieldGroup[];
    onChange: (groups: CustomFieldGroup[]) => void;
    mediaUrl?: string;
    uploadUrl?: string;
}) {
    const t = useAdminTranslation();
    return (
        <>
            {groups.map((group, index) => (
                <Card key={group.id} className="p-5">
                    <h2 className="mb-5 font-semibold">{group.title}</h2>
                    <Fields
                        fields={group.items}
                        onChange={(items) =>
                            onChange(
                                groups.map((item, i) =>
                                    i === index ? { ...item, items } : item,
                                ),
                            )
                        }
                        mediaUrl={mediaUrl}
                        uploadUrl={uploadUrl}
                    />
                </Card>
            ))}
        </>
    );
}
