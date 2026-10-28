import { SettingsSectionNav, type EmailNavigation } from "./section-nav";
import { SettingsSection } from "./settings-section";
import { Head, Link } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Field } from "@admin/components/field";
import { Input } from "@admin/components/ui/input";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardFooter,
} from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";
export default function EmailPreview({
    emailNavigation,
    variables,
    values,
    back,
    iframe,
}: {
    emailNavigation?: EmailNavigation;
    variables: Record<string, string>;
    values: Record<string, string>;
    back: string;
    iframe: string;
}) {
    const t = useAdminTranslation();
    const [data, setData] = useState(values);
    const [src, setSrc] = useState(iframe);
    return (
        <AdminLayout title={t("Email preview")}>
            <Head title={t("Email preview")} />
            <SettingsSection
                description={t(
                    "Check the rendered email and adjust sample values before updating the preview.",
                )}
                navigation={
                    <div className="space-y-4">
                        <Button variant="outline" asChild>
                            <Link href={back}>{t("Back to editor")}</Link>
                        </Button>
                        <SettingsSectionNav links={emailNavigation} />
                    </div>
                }
            >
                {!!Object.keys(variables).length && (
                    <Card className="min-w-0 [&_label]:break-words">
                        <CardHeader>
                            <CardTitle>{t("Preview values")}</CardTitle>
                        </CardHeader>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                const url = new URL(
                                    iframe,
                                    window.location.origin,
                                );
                                Object.entries(data).forEach(([key, value]) =>
                                    url.searchParams.set(key, value),
                                );
                                setSrc(url.toString());
                            }}
                        >
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                {Object.entries(variables).map(
                                    ([key, label]) => (
                                        <Field
                                            key={key}
                                            label={String(label).replace(
                                                /<[^>]*>/g,
                                                "",
                                            )}
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    value={data[key] ?? ""}
                                                    onChange={(e) =>
                                                        setData({
                                                            ...data,
                                                            [key]: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            )}
                                        </Field>
                                    ),
                                )}
                            </CardContent>
                            <CardFooter>
                                <Button type="submit">
                                    {t("Update preview")}
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                )}
                <Card className="overflow-hidden">
                    <iframe
                        title={t("Rendered email preview")}
                        src={src}
                        sandbox=""
                        className="min-h-[45rem] w-full bg-white"
                    />
                </Card>
            </SettingsSection>
        </AdminLayout>
    );
}
