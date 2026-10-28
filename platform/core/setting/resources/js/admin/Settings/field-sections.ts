type Field = { name: string; conditions?: { name: string; value: string }[] };
export function fieldSections<T extends Field>(
    fields: T[],
    kind?: "email" | "media" | "email-template" | null,
): { key: string; title: string; fields: T[] }[] {
    if (!kind) return [{ key: "general", title: "", fields }];
    const groups =
        kind === "email-template"
            ? [
                  { key: "branding", title: "Email appearance" },
                  { key: "styles", title: "Custom CSS" },
                  { key: "social", title: "Social Links" },
              ]
            : kind === "email"
              ? [
                    { key: "connection", title: "Mail connection" },
                    { key: "sender", title: "Sender identity" },
                ]
              : [
                    { key: "storage", title: "Storage" },
                    { key: "uploads", title: "Uploads and paths" },
                    { key: "images", title: "Image processing" },
                    { key: "watermark", title: "Watermark" },
                    { key: "thumbnails", title: "Thumbnails" },
                ];
    const key = (field: T) => {
        if (kind === "email-template")
            return field.name.endsWith("_custom_css")
                ? "styles"
                : field.name.endsWith("_social_links")
                  ? "social"
                  : "branding";
        if (kind === "email")
            return field.name.startsWith("email_from_")
                ? "sender"
                : "connection";
        if (
            field.name === "media_driver" ||
            field.conditions?.some((item) => item.name === "media_driver")
        )
            return "storage";
        if (field.name === "media_convert_file_name_to_uuid") return "uploads";
        if (/watermark/.test(field.name)) return "watermark";
        if (
            /thumbnail|media_sizes?_|media_size_|enable_thumbnail/.test(
                field.name,
            )
        )
            return "thumbnails";
        if (
            /image|convert|exif|orientation|processing|quality|placeholder/.test(
                field.name,
            )
        )
            return "images";
        return "uploads";
    };
    return groups
        .map((group) => ({
            ...group,
            fields: fields.filter((field) => key(field) === group.key),
        }))
        .filter((group) => group.fields.length);
}

export function isSettingFieldVisible(
    field: Field,
    values: Record<string, unknown>,
): boolean {
    return !field.conditions?.some(
        (condition) =>
            String(
                values[condition.name] === true ? 1 : values[condition.name],
            ) !== condition.value,
    );
}
