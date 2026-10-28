export type CustomField = {
    id: string | number;
    title: string;
    slug: string;
    type: string;
    value?: string | string[] | CustomField[][];
    instructions?: string;
    options?: {
        defaultValue?: string;
        defaultValueTextarea?: string;
        selectChoices?: string;
        placeholderText?: string;
        rows?: number;
        buttonLabel?: string;
    };
    items?: CustomField[];
    [key: string]: unknown;
};
export type CustomFieldGroup = {
    id: string | number;
    title: string;
    items: CustomField[];
    [key: string]: unknown;
};
export function initializeCustomFields(
    groups: CustomFieldGroup[],
): CustomFieldGroup[] {
    function initialize(items: CustomField[]): CustomField[] {
        return items.map((field) => ({
            ...field,
            value:
                field.type === "repeater"
                    ? Array.isArray(field.value)
                        ? (field.value as CustomField[][]).map(initialize)
                        : []
                    : field.type === "checkbox"
                      ? Array.isArray(field.value)
                          ? field.value
                          : []
                      : (field.value ??
                        (["wysiwyg", "textarea"].includes(field.type)
                            ? field.options?.defaultValueTextarea
                            : field.options?.defaultValue) ??
                        ""),
            items: field.items ? initialize(field.items) : undefined,
        }));
    }
    return groups.map((group) => ({
        ...group,
        items: initialize(group.items),
    }));
}
