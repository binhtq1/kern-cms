import { CodeEditor } from "@admin/components/code-editor";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { useForm } from "@inertiajs/react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Card, CardContent } from "@admin/components/ui/card";
import { Field } from "@admin/components/field";
import { FileUploadField } from "@admin/components/file-upload-field";
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
function editorValues(
    fields: Props["fields"],
): Record<string, string | File | null> {
    return Object.fromEntries(
        fields
            .filter((field) => field.type !== "notice")
            .map((field) => [
                field.name,
                field.type === "file" ? null : (field.value ?? ""),
            ]),
    );
}
export default function Editor({ title, submit, fields }: Props) {
    const t = useAdminTranslation();
    const form = useForm(editorValues(fields));
    useUnsavedChanges(form.isDirty, form.processing);
    const hasFile = Object.values(form.data).some(
        (value) => value instanceof File,
    );
    return (
        <AdminLayout title={t(title)}>
            <form
                className="space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(submit, {
                        onSuccess: (page) => {
                            if (hasFile && Array.isArray(page.props.fields)) {
                                const saved = editorValues(page.props.fields);
                                form.setData(saved);
                                form.setDefaults(saved);
                            } else {
                                form.setDefaults(form.data);
                            }
                        },
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
                                    {(id) => {
                                        const value = form.data[field.name];
                                        return field.type === "file" ? (
                                            <FileUploadField
                                                id={id}
                                                accept="text/plain,.txt"
                                                formats=".txt"
                                                maxBytes={2 * 1024 * 1024}
                                                disabled={form.processing}
                                                file={
                                                    value instanceof File
                                                        ? value
                                                        : null
                                                }
                                                onSelect={(file) => {
                                                    form.clearErrors(
                                                        field.name,
                                                    );
                                                    form.setData(
                                                        field.name,
                                                        file,
                                                    );
                                                }}
                                                onRemove={() => {
                                                    form.clearErrors(
                                                        field.name,
                                                    );
                                                    form.setData(
                                                        field.name,
                                                        null,
                                                    );
                                                }}
                                            />
                                        ) : (
                                            <CodeEditor
                                                id={id}
                                                mode={field.mode}
                                                value={String(value ?? "")}
                                                disabled={
                                                    hasFile && form.processing
                                                }
                                                onChange={(value) =>
                                                    form.setData(
                                                        field.name,
                                                        value,
                                                    )
                                                }
                                            />
                                        );
                                    }}
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
