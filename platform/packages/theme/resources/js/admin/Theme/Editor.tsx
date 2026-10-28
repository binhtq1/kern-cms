import { CodeEditor } from "@admin/components/code-editor";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { useForm } from "@inertiajs/react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Card, CardContent } from "@admin/components/ui/card";
import { Field } from "@admin/components/field";
import { Input, Textarea } from "@admin/components/ui/input";
import { Button } from "@admin/components/ui/button";
import { useAdminTranslation } from "@admin/lib/translate";
type Props = {
    title: string;
    submit: string;
    fields: {
        name: string;
        label: string;
        value: string;
        type: string;
        mode?: string;
        description: string;
    }[];
};
export default function Editor({ title, submit, fields }: Props) {
    const t = useAdminTranslation();
    const form = useForm<Record<string, string | File | null>>(
        Object.fromEntries(
            fields
                .filter((field) => field.type !== "notice")
                .map((field) => [
                    field.name,
                    field.type === "file" ? null : (field.value ?? ""),
                ]),
        ),
    );
    useUnsavedChanges(form.isDirty, form.processing);
    return (
        <AdminLayout title={t(title)}>
            <form
                className="space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(submit, {
                        onSuccess: () => form.setDefaults(form.data),
                    });
                }}
            >
                <Card>
                    <CardContent className="space-y-5 p-5">
                        {fields.map((field) =>
                            field.type === "notice" ? (
                                <p
                                    key={field.name}
                                    role="status"
                                    className="text-sm text-muted-foreground"
                                >
                                    {field.description}
                                </p>
                            ) : (
                                <Field
                                    key={field.name}
                                    label={field.label}
                                    description={field.description}
                                    error={form.errors[field.name]}
                                >
                                    {(id) =>
                                        field.type === "file" ? (
                                            <Input
                                                id={id}
                                                type="file"
                                                accept="text/plain,.txt"
                                                onChange={(event) =>
                                                    form.setData(
                                                        field.name,
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                        ) : (
                                            <CodeEditor
                                                id={id}
                                                mode={field.mode}
                                                value={String(
                                                    form.data[field.name] ?? "",
                                                )}
                                                onChange={(value) =>
                                                    form.setData(
                                                        field.name,
                                                        value,
                                                    )
                                                }
                                            />
                                        )
                                    }
                                </Field>
                            ),
                        )}
                    </CardContent>
                </Card>
                <div className="flex justify-end">
                    <Button type="submit" loading={form.processing}>
                        {t("Save")}
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
