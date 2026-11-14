import type { FieldItem as Item } from "../FieldGroups/field-group-state";
export const typeLabels: Record<string, string> = {
    text: "Text field",
    textarea: "Textarea",
    number: "Number",
    email: "Email",
    password: "Password",
    wysiwyg: "WYSIWYG editor",
    image: "Image",
    file: "File",
    select: "Select",
    checkbox: "Checkbox",
    radio: "Radio",
    repeater: "Repeater",
    date: "Date",
    time: "Time",
    color: "Color",
};
export const newItem = (): Item => ({
    id: null,
    title: "",
    slug: "",
    type: "text",
    instructions: "",
    options: {},
    items: [],
});
