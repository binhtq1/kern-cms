export type Value =
    string | number | boolean | null | string[] | number[] | LinkData[];
export type Item = { key: string; id: string; data: Record<string, Value> };
export type Group = {
    id: string;
    name: string;
    description?: string;
    items: Item[];
};
export type WidgetField = {
    name: string;
    label: string;
    type: string;
    multiple: boolean;
    choices: { value: string; label: string }[];
    text?: string;
};
export type Definition = {
    id: string;
    name: string;
    description?: string;
    defaults: Record<string, Value>;
    fields: WidgetField[];
};
export type LinkData = { key: string; value: string }[];
