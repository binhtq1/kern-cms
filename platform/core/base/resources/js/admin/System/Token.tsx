import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Field } from "@admin/components/field";
import { Input } from "@admin/components/ui/input";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent, CardFooter } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";
export default function Token({ submit }: { submit: string }) {
    const t = useAdminTranslation();
    const form = useForm({ name: "" });
    return (
        <AdminLayout title={t("Create token")}>
            <Head title={t("Create token")} />
            <form
                className="max-w-xl"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(submit);
                }}
            >
                <Card>
                    <CardContent>
                        <Field
                            label={t("Name")}
                            error={form.errors.name}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    required
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                />
                            )}
                        </Field>
                    </CardContent>
                    <CardFooter>
                        <Button type="submit" loading={form.processing}>
                            {t("Create token")}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AdminLayout>
    );
}
