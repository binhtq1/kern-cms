import { Select } from "./ui/select";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { useAdminTranslation } from "@admin/lib/translate";
import type { Option } from "@admin/types";
export type RegisteredFilter = {
    field: string;
    operator: string;
    value: string;
};
export type RegisteredField = {
    name: string;
    label: string;
    type: string;
    choices: Option[];
    operators: string[];
    editable: boolean;
};
export function RegisteredBulkChange({
    fields,
    name,
    value,
    onNameChange,
    onValueChange,
    onApply,
    busy,
}: {
    fields: RegisteredField[];
    name: string;
    value: string;
    onNameChange: (name: string) => void;
    onValueChange: (value: string) => void;
    onApply: () => void;
    busy: boolean;
}) {
    const t = useAdminTranslation();
    const field = fields.find((field) => field.name === name);
    const editable = fields.filter(
        (field) => field.editable && field.name !== "status",
    );
    if (!editable.length) return null;
    return (
        <>
            <Select
                aria-label={t("Bulk change field")}
                value={name}
                disabled={busy}
                onValueChange={(value) => {
                    onNameChange(value);
                    onValueChange("");
                }}
                className="w-44"
            >
                <option value="">{t("Choose field")}</option>
                {editable.map((field) => (
                    <option key={field.name} value={field.name}>
                        {field.label}
                    </option>
                ))}
            </Select>
            {field &&
                (field.type === "select" ? (
                    <Select
                        aria-label={field.label}
                        value={value}
                        disabled={busy}
                        onValueChange={onValueChange}
                        className="w-48"
                    >
                        {!field.choices.some(
                            (choice) => choice.value === "",
                        ) && <option value="">{t("Select")}</option>}
                        {field.choices.map((choice) => (
                            <option key={choice.value} value={choice.value}>
                                {choice.label}
                            </option>
                        ))}
                    </Select>
                ) : (
                    <Input
                        aria-label={field.label}
                        type={field.type}
                        value={value}
                        disabled={busy}
                        onChange={(event) => onValueChange(event.target.value)}
                        className="w-48"
                    />
                ))}
            <Button
                variant="outline"
                size="sm"
                disabled={busy || !field || !value}
                onClick={onApply}
            >
                {t("Apply change")}
            </Button>
        </>
    );
}
export function RegisteredFilterEditor({
    fields,
    value,
    onChange,
    onApply,
}: {
    fields: RegisteredField[];
    value: RegisteredFilter[];
    onChange: (value: RegisteredFilter[]) => void;
    onApply: () => void;
}) {
    const t = useAdminTranslation();
    return (
        <div className="space-y-3 border-b bg-muted/20 p-4">
            {value.map((filter, index) => {
                const field = fields.find(
                    (field) => field.name === filter.field,
                );
                const update = (changes: Partial<RegisteredFilter>) =>
                    onChange(
                        value.map((row, i) =>
                            i === index ? { ...row, ...changes } : row,
                        ),
                    );
                return (
                    <div key={index} className="flex flex-wrap gap-2">
                        <Select
                            aria-label={`${t("Filter field")} ${index + 1}`}
                            value={filter.field}
                            onValueChange={(name) =>
                                update({
                                    field: name,
                                    operator:
                                        fields.find(
                                            (field) => field.name === name,
                                        )?.operators[0] ?? "=",
                                    value: "",
                                })
                            }
                            className="w-44"
                        >
                            {fields.map((field) => (
                                <option key={field.name} value={field.name}>
                                    {field.label}
                                </option>
                            ))}
                        </Select>
                        <Select
                            aria-label={`${t("Operator")} ${index + 1}`}
                            value={filter.operator}
                            onValueChange={(operator) => update({ operator })}
                            className="w-36"
                        >
                            {(field?.operators ?? ["="]).map((operator) => (
                                <option key={operator} value={operator}>
                                    {t(
                                        operator === "like"
                                            ? "Contains"
                                            : operator === "="
                                              ? "Equals"
                                              : operator === ">="
                                                ? "On or after"
                                                : "On or before",
                                    )}
                                </option>
                            ))}
                        </Select>
                        {field?.type === "select" ? (
                            <Select
                                aria-label={`${t("Filter value")} ${index + 1}`}
                                value={filter.value}
                                onValueChange={(next) =>
                                    update({ value: next })
                                }
                                className="w-56"
                            >
                                {!field.choices.some(
                                    (choice) => choice.value === "",
                                ) && <option value="">{t("Select")}</option>}
                                {field.choices.map((choice) => (
                                    <option
                                        key={choice.value}
                                        value={choice.value}
                                    >
                                        {choice.label}
                                    </option>
                                ))}
                            </Select>
                        ) : (
                            <Input
                                aria-label={`${t("Filter value")} ${index + 1}`}
                                type={field?.type === "date" ? "date" : "text"}
                                value={filter.value}
                                required
                                onChange={(event) =>
                                    update({ value: event.target.value })
                                }
                                className="w-56"
                            />
                        )}
                        <Button
                            variant="ghost"
                            aria-label={`${t("Remove filter")} ${index + 1}`}
                            onClick={() =>
                                onChange(value.filter((_, i) => i !== index))
                            }
                        >
                            {t("Remove")}
                        </Button>
                    </div>
                );
            })}
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    disabled={value.length >= 10 || !fields.length}
                    onClick={() =>
                        onChange([
                            ...value,
                            {
                                field: fields[0].name,
                                operator: fields[0].operators[0],
                                value: "",
                            },
                        ])
                    }
                >
                    {t("Add filter")}
                </Button>
                <Button
                    disabled={value.some((filter) => !filter.value)}
                    onClick={onApply}
                >
                    {t("Apply filters")}
                </Button>
            </div>
        </div>
    );
}
