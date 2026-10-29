import type { ContentFormProps } from "@admin/components/content-resource-form";

function record(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === "object" && !Array.isArray(value);
}

export function isCategoryFormSchema(
    value: unknown,
): value is ContentFormProps {
    if (!record(value) || value.resource !== "categories") return false;
    if (
        typeof value.title !== "string" ||
        !(value.id === null || typeof value.id === "string") ||
        !record(value.values) ||
        !record(value.urls) ||
        !record(value.permissions)
    )
        return false;
    const urls = value.urls;
    const permissions = value.permissions;
    if (
        !["index", "submit", "media", "mediaUpload"].every(
            (name) => typeof urls[name] === "string" && urls[name],
        ) ||
        !["create", "edit"].every(
            (name) => typeof permissions[name] === "boolean",
        ) ||
        !["translatable", "slugEnabled", "seoEnabled", "galleryEnabled"].every(
            (name) => typeof value[name] === "boolean",
        )
    )
        return false;
    if (
        !Array.isArray(value.fields) ||
        !value.fields.some((field) => record(field) && field.name === "name") ||
        !value.fields.every(
            (field) =>
                record(field) &&
                typeof field.name === "string" &&
                typeof field.label === "string" &&
                typeof field.type === "string" &&
                Array.isArray(field.choices) &&
                field.choices.every(
                    (choice) =>
                        record(choice) &&
                        typeof choice.value === "string" &&
                        typeof choice.label === "string",
                ),
        ) ||
        !Array.isArray(value.languages) ||
        !value.languages.every(
            (language) =>
                record(language) &&
                typeof language.name === "string" &&
                typeof language.url === "string" &&
                typeof language.active === "boolean",
        )
    )
        return false;
    return [
        "gallery",
        "customFieldGroups",
        "shortcodes",
        "fieldOptions",
        "replies",
    ].every((name) => Array.isArray(value[name]));
}
