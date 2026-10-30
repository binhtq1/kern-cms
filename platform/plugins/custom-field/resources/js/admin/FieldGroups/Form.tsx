import { Field } from "@admin/components/field";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    useDraftBaseline,
    useUnsavedChanges,
} from "@admin/lib/use-unsaved-changes";
import { Head, Link, useForm } from "@inertiajs/react";
import { Download } from "lucide-react";
import { useState } from "react";
import { Builder } from "../components/field-builder";
import { FieldDisplayRules } from "../components/field-display-rules";
import {
    normalizeFieldItems,
    savedFieldGroupState,
    type DisplayRule,
    type FieldItem as Item,
} from "./field-group-state";

type RuleSchema = Record<
    string,
    {
        items: Record<
            string,
            { slug: string; title: string; data: Record<string, string> }
        >;
    }
>;
type Props = {
    group: {
        id?: string;
        title: string;
        status: string;
        order: number;
        rules: DisplayRule[][];
        items: Item[];
    };
    types: string[];
    ruleSchema: RuleSchema;
    permissions: { create: boolean; edit: boolean };
    urls: { index: string; submit: string; export: string };
};

export default function FieldGroupForm({
    group,
    types,
    ruleSchema,
    permissions,
    urls,
}: Props) {
    const t = useAdminTranslation();
    const [items, setItems] = useState(() => normalizeFieldItems(group.items));
    const [rules, setRules] = useState(group.rules ?? []);
    const [deleted, setDeleted] = useState<string[]>([]);
    const [submitter, setSubmitter] = useState("save");
    const form = useForm({
        title: group.title,
        status: group.status,
        order: group.order,
    });
    const draft = useDraftBaseline({ items, rules, deleted });
    useUnsavedChanges(form.isDirty || draft.dirty, form.processing);
    const schema = Object.values(ruleSchema).flatMap((group) =>
        Object.values(group.items),
    );
    const canSave = group.id ? permissions.edit : permissions.create;
    function remove(item: Item) {
        const ids: string[] = [];
        function collect(item: Item) {
            if (item.id) ids.push(item.id);
            item.items.forEach(collect);
        }
        collect(item);
        setDeleted((current) => [...current, ...ids]);
    }
    function addRule() {
        return {
            name: schema[0]?.slug ?? "",
            type: "==",
            value: Object.keys(schema[0]?.data ?? {})[0] ?? "",
        };
    }
    return (
        <AdminLayout
            title={t(group.id ? "Edit field group" : "Create field group")}
            description={group.id ? group.title : undefined}
            actions={
                <Button asChild variant="outline">
                    <Link href={urls.index}>{t("Back")}</Link>
                </Button>
            }
        >
            <Head
                title={t(group.id ? "Edit field group" : "Create field group")}
            />
            <form
                className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_280px]"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        submitter,
                        group_items: JSON.stringify(items),
                        rules: JSON.stringify(rules),
                        deleted_items: JSON.stringify(deleted),
                    }));
                    form.post(urls.submit, {
                        preserveScroll: true,
                        onSuccess: (page) => {
                            const savedGroup = page.props.group as
                                Props["group"] | undefined;
                            const saved = savedFieldGroupState(
                                savedGroup,
                                page.props.errors,
                                Boolean(
                                    (
                                        page.props.flash as
                                            { error?: string } | undefined
                                    )?.error,
                                ),
                            );
                            if (!saved || !savedGroup) {
                                form.setDefaults({
                                    title: group.title,
                                    status: group.status,
                                    order: group.order,
                                });
                                return;
                            }
                            setItems(saved.items);
                            setRules(saved.rules);
                            setDeleted(saved.deleted);
                            const values = {
                                title: savedGroup.title,
                                status: savedGroup.status,
                                order: savedGroup.order,
                            };
                            form.setData(values);
                            form.setDefaults(values);
                            draft.saved(saved);
                        },
                    });
                }}
            >
                <div className="space-y-5">
                    <Card className="p-5">
                        <Field
                            label={t("Title")}
                            error={form.errors.title}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData("title", e.target.value)
                                    }
                                    required
                                />
                            )}
                        </Field>
                    </Card>
                    <Builder
                        items={items}
                        onChange={setItems}
                        types={types}
                        onRemove={remove}
                    />
                    <FieldDisplayRules
                        rules={rules}
                        schema={schema}
                        setRules={setRules}
                        addRule={addRule}
                    />
                    {Object.entries(form.errors).map(([key, value]) => (
                        <p
                            key={key}
                            role="alert"
                            className="text-sm text-destructive"
                        >
                            {value}
                        </p>
                    ))}
                </div>
                <Card className="space-y-5 p-5 lg:sticky lg:top-24">
                    <Field label={t("Status")}>
                        {(id) => (
                            <Select
                                id={id}
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData("status", value)
                                }
                            >
                                {["published", "draft", "pending"].map(
                                    (status) => (
                                        <option key={status} value={status}>
                                            {t(
                                                status.charAt(0).toUpperCase() +
                                                    status.slice(1),
                                            )}
                                        </option>
                                    ),
                                )}
                            </Select>
                        )}
                    </Field>
                    <Field label={t("Order")}>
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                min={0}
                                max={127}
                                value={form.data.order}
                                onChange={(e) =>
                                    form.setData(
                                        "order",
                                        Number(e.target.value),
                                    )
                                }
                            />
                        )}
                    </Field>
                    {canSave && (
                        <>
                            <Button
                                type="submit"
                                className="w-full"
                                loading={form.processing}
                                onClick={() => setSubmitter("save")}
                            >
                                {t("Save")}
                            </Button>
                            <Button
                                type="submit"
                                variant="outline"
                                className="w-full"
                                disabled={form.processing}
                                onClick={() => setSubmitter("apply")}
                            >
                                {t("Save and continue")}
                            </Button>
                        </>
                    )}
                    {group.id && (
                        <Button asChild variant="outline" className="w-full">
                            <a href={urls.export}>
                                <Download />
                                {t("Export")}
                            </a>
                        </Button>
                    )}
                </Card>
            </form>
        </AdminLayout>
    );
}
