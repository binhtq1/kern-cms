import type { InertiaFormProps } from "@inertiajs/react";
import { CodeEditor } from "./code-editor";
import type {
    ContentFormProps,
    SchemaField,
    Value,
} from "./content-resource-form";
import { CategoryTree } from "./category-tree";
import { Field } from "./field";
import { FontPicker } from "./font-picker";
import { IconPicker } from "./icon-picker";
import { MediaPicker } from "./media-picker";
import { RichEditor } from "./rich-editor";
import { SafeRichText } from "./safe-rich-text";
import { TagsInput } from "./tags-input";
import { Checkbox } from "./ui/checkbox";
import { Input, Textarea } from "./ui/input";
import { Radio, RadioGroup } from "./ui/radio";
import { Select } from "./ui/select";
export function ContentSchemaField({
    field,
    id,
    changingPassword,
    canSave,
    embeddedBusy,
    form,
    urls,
    shortcodes,
}: {
    field: SchemaField;
    id?: string | null;
    changingPassword: boolean;
    canSave: boolean;
    embeddedBusy: boolean;
    form: InertiaFormProps<Record<string, Value>>;
    urls: ContentFormProps["urls"];
    shortcodes: ContentFormProps["shortcodes"];
}) {
    const text = (name: string) => String(form.data[name] ?? "");

    if (
        field.type === "hidden" ||
        (field.name === "is_change_password" && !id) ||
        (field.type === "password" && !changingPassword)
    )
        return null;
    const value = text(field.name);
    const disabled = field.readonly || !canSave || embeddedBusy;
    return (
        <Field
            key={field.name}
            label={field.label}
            description={
                field.description_html ? (
                    <SafeRichText html={field.description_html} />
                ) : (
                    field.description
                )
            }
            required={field.required}
            error={form.errors[field.name]}
            group={["radio", "categories"].includes(field.type)}
        >
            {(controlId) => {
                if (field.type === "checkbox")
                    return (
                        <Checkbox
                            id={controlId}
                            checked={Boolean(Number(form.data[field.name]))}
                            disabled={disabled}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    field.name,
                                    checked === true ? 1 : 0,
                                )
                            }
                        />
                    );
                if (field.type === "tags")
                    return (
                        <TagsInput
                            id={controlId}
                            label={field.label}
                            value={value}
                            options={field.choices.map(
                                (choice) => choice.value,
                            )}
                            onValueChange={(value) =>
                                form.setData(field.name, value)
                            }
                            disabled={!canSave}
                            readOnly={field.readonly}
                            placeholder={field.placeholder}
                        />
                    );
                if (field.type === "select")
                    return (
                        <Select
                            id={controlId}
                            value={value}
                            disabled={disabled}
                            onValueChange={(value) =>
                                form.setData(field.name, value)
                            }
                        >
                            {field.choices.map((choice) => (
                                <option key={choice.value} value={choice.value}>
                                    {choice.label}
                                </option>
                            ))}
                        </Select>
                    );
                if (field.type === "radio")
                    return (
                        <RadioGroup
                            className="space-y-2"
                            id={controlId}
                            name={controlId}
                            value={value}
                            disabled={disabled}
                            onValueChange={(next) =>
                                form.setData(field.name, next)
                            }
                            aria-labelledby={`${controlId}-label`}
                        >
                            {field.choices.map((choice) => (
                                <label
                                    key={choice.value}
                                    className="flex items-center gap-2"
                                >
                                    <Radio value={choice.value} />
                                    {choice.label}
                                </label>
                            ))}
                        </RadioGroup>
                    );
                if (field.type === "categories")
                    return (
                        <CategoryTree
                            choices={field.choices}
                            value={(form.data[field.name] ?? []) as string[]}
                            disabled={disabled}
                            onValueChange={(value) =>
                                form.setData(field.name, value)
                            }
                        />
                    );
                if (field.type === "code")
                    return (
                        <CodeEditor
                            id={controlId}
                            value={value}
                            mode={field.mode}
                            disabled={disabled}
                            placeholder={field.placeholder}
                            onChange={(value) =>
                                form.setData(field.name, value)
                            }
                        />
                    );
                if (field.type === "font")
                    return (
                        <FontPicker
                            id={controlId}
                            value={value}
                            choices={field.choices}
                            disabled={disabled}
                            placeholder={field.placeholder}
                            onChange={(value) =>
                                form.setData(field.name, value)
                            }
                        />
                    );
                if (field.type === "icon")
                    return (
                        <IconPicker
                            id={controlId}
                            value={value}
                            iconsUrl={field.iconsUrl}
                            disabled={disabled}
                            placeholder={field.placeholder}
                            onChange={(value) =>
                                form.setData(field.name, value)
                            }
                        />
                    );
                if (disabled)
                    return <Textarea id={controlId} readOnly value={value} />;
                if (field.type === "editor")
                    return (
                        <RichEditor
                            id={controlId}
                            value={value}
                            onChange={(html) => form.setData(field.name, html)}
                            mediaUrl={urls.media}
                            uploadUrl={urls.mediaUpload}
                            shortcodes={shortcodes}
                        />
                    );
                if (["image", "file"].includes(field.type))
                    return (
                        <MediaPicker
                            variant="featured"
                            id={controlId}
                            aspect={
                                /banner|cover/.test(field.name)
                                    ? "banner"
                                    : "landscape"
                            }
                            value={value}
                            onChange={(url) => form.setData(field.name, url)}
                            listUrl={urls.media}
                            uploadUrl={urls.mediaUpload}
                            imagesOnly={field.type === "image"}
                        />
                    );
                if (field.type === "textarea")
                    return (
                        <Textarea
                            id={controlId}
                            value={value}
                            onChange={(event) =>
                                form.setData(field.name, event.target.value)
                            }
                            placeholder={field.placeholder}
                            maxLength={field.maxLength}
                            className={
                                field.name === "raw_content"
                                    ? "min-h-40 font-mono"
                                    : undefined
                            }
                        />
                    );
                return (
                    <Input
                        id={controlId}
                        type={
                            field.type === "date"
                                ? "date"
                                : field.type === "password"
                                  ? "password"
                                  : field.type === "number"
                                    ? "number"
                                    : field.name === "email"
                                      ? "email"
                                      : "text"
                        }
                        value={value}
                        placeholder={field.placeholder}
                        maxLength={field.maxLength}
                        autoComplete={
                            field.type === "password"
                                ? "new-password"
                                : undefined
                        }
                        onChange={(event) =>
                            form.setData(field.name, event.target.value)
                        }
                    />
                );
            }}
        </Field>
    );
}
