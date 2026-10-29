import { SettingsSectionNav, type EmailNavigation } from "./section-nav";
import { SettingsSection, SettingsSaveFooter } from "./settings-section";
import { notify } from "@admin/components/toast";
import { Copy, Plus } from "lucide-react";
import { CodeEditor } from "@admin/components/code-editor";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { Head, Link, useForm } from "@inertiajs/react";
import { useState, useRef } from "react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Field } from "@admin/components/field";
import { Input, Textarea } from "@admin/components/ui/input";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
import { useAdminTranslation } from "@admin/lib/translate";
import { copyToClipboard } from "@admin/lib/clipboard";
export default function EmailTemplate({
    title,
    emailNavigation,
    languageNavigation = [],
    content,
    subject,
    subjectKey,
    module,
    template,
    submit,
    restore,
    preview,
    variables,
    functions = {},
    icons = {},
    back,
    hasSubject = Boolean(subject),
}: {
    title: string;
    emailNavigation?: EmailNavigation;
    languageNavigation?: { label: string; url: string; active: boolean }[];
    content: string;
    subject: string;
    subjectKey: string;
    module: string;
    template: string;
    submit: string;
    restore: string;
    preview: string;
    variables: Record<string, string>;
    functions?: Record<string, { label: string; sample: string }>;
    icons?: Record<string, string>;
    back: string;
    hasSubject?: boolean;
}) {
    const t = useAdminTranslation();
    const [confirm, setConfirm] = useState(false);
    const [restoring, setRestoring] = useState(false);
    const requestPending = useRef(false);
    const form = useForm({
        email_content: content,
        email_subject: subject ?? "",
        ...(hasSubject ? { email_subject_key: subjectKey } : {}),
        module,
        template_file: template,
    });
    const latestData = useRef(form.data);
    latestData.current = form.data;
    useUnsavedChanges(form.isDirty, form.processing);
    function insertSnippet(snippet: string) {
        form.setData(
            "email_content",
            `${latestData.current.email_content}${latestData.current.email_content.endsWith("\n") ? "" : "\n"}${snippet}`,
        );
    }
    async function copySnippet(snippet: string) {
        try {
            await copyToClipboard(snippet);
            notify(t("Copied"));
        } catch {
            notify(
                t("Could not copy. Select and copy the code manually."),
                true,
            );
        }
    }
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (requestPending.current || form.processing) return;
                    form.put(submit, {
                        preserveScroll: true,
                        onStart: () => {
                            requestPending.current = true;
                        },
                        onSuccess: () => form.setDefaults(form.data),
                        onFinish: () => {
                            requestPending.current = false;
                        },
                    });
                }}
            >
                <SettingsSection
                    description={t(
                        "Edit the email content and use variables to personalize each message.",
                    )}
                    navigation={
                        emailNavigation?.length ? (
                            <SettingsSectionNav links={emailNavigation} />
                        ) : (
                            <Link
                                href={back}
                                className="text-sm text-muted-foreground hover:text-foreground"
                            >
                                {t("Back to email templates")}
                            </Link>
                        )
                    }
                >
                    {languageNavigation.length > 1 && (
                        <nav
                            aria-label={t("Language")}
                            className="mb-4 flex flex-wrap gap-2"
                        >
                            {languageNavigation.map((language) => (
                                <Button
                                    key={language.url}
                                    asChild
                                    size="sm"
                                    variant={
                                        language.active ? "default" : "outline"
                                    }
                                >
                                    <Link
                                        href={language.url}
                                        aria-current={
                                            language.active ? "page" : undefined
                                        }
                                    >
                                        {language.label}
                                    </Link>
                                </Button>
                            ))}
                        </nav>
                    )}
                    <Card>
                        <CardContent className="space-y-6 divide-y [&>div]:pt-5 [&>div:first-child]:pt-0">
                            {hasSubject && (
                                <Field
                                    label={t("Subject")}
                                    error={form.errors.email_subject}
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            value={form.data.email_subject}
                                            onChange={(e) =>
                                                form.setData(
                                                    "email_subject",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            )}
                            <Field
                                label={t("Content")}
                                error={form.errors.email_content}
                            >
                                {(id) => (
                                    <CodeEditor
                                        id={id}
                                        mode="html"
                                        value={form.data.email_content}
                                        onChange={(value) =>
                                            form.setData("email_content", value)
                                        }
                                    />
                                )}
                            </Field>
                        </CardContent>
                        <SettingsSaveFooter
                            processing={form.processing && !restoring}
                            disabled={form.processing || restoring}
                            dirty={form.isDirty}
                        >
                            <Button variant="outline" asChild>
                                <a
                                    href={preview}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {t("Preview")}
                                </a>
                            </Button>
                            <Button
                                variant="ghost"
                                disabled={form.processing || restoring}
                                onClick={() => setConfirm(true)}
                            >
                                {t("Restore default")}
                            </Button>
                        </SettingsSaveFooter>
                    </Card>
                    <div className="grid min-w-0 items-start gap-4 xl:grid-cols-3">
                        <Card>
                            <details>
                                <summary className="cursor-pointer px-5 py-4 text-sm font-semibold">
                                    {t("Available variables")}
                                </summary>
                                <CardContent className="max-h-[50dvh] overflow-y-auto border-t">
                                    <dl className="space-y-4 text-sm">
                                        {Object.entries(variables).map(
                                            ([key, value]) => (
                                                <div key={key}>
                                                    <dt className="break-all font-mono">{`{{ ${key} }}`}</dt>
                                                    <dd className="break-words text-muted-foreground">
                                                        {String(value).replace(
                                                            /<[^>]*>/g,
                                                            "",
                                                        )}
                                                    </dd>
                                                    <div className="mt-2 flex gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                insertSnippet(
                                                                    `{{ ${key} }}`,
                                                                )
                                                            }
                                                        >
                                                            <Plus aria-hidden="true" />
                                                            {t("Insert")}
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                void copySnippet(
                                                                    `{{ ${key} }}`,
                                                                )
                                                            }
                                                        >
                                                            <Copy aria-hidden="true" />
                                                            {t("Copy")}
                                                        </Button>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                </CardContent>
                            </details>
                        </Card>
                        <Card>
                            <details>
                                <summary className="cursor-pointer px-5 py-4 text-sm font-semibold">
                                    {t("Functions")}
                                </summary>
                                <CardContent className="max-h-[50dvh] space-y-4 overflow-y-auto border-t">
                                    {Object.entries(functions).map(
                                        ([name, item]) => (
                                            <div
                                                key={name}
                                                className="space-y-2 border-b pb-4 last:border-b-0"
                                            >
                                                <p className="text-sm">
                                                    {item.label}
                                                </p>
                                                <pre className="overflow-auto rounded border bg-muted/30 p-3 text-xs">
                                                    {item.sample}
                                                </pre>
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            insertSnippet(
                                                                item.sample,
                                                            )
                                                        }
                                                    >
                                                        {t("Insert")}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            void copySnippet(
                                                                item.sample,
                                                            )
                                                        }
                                                    >
                                                        {t("Copy")}
                                                    </Button>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </CardContent>
                            </details>
                        </Card>
                        <Card>
                            <details>
                                <summary className="cursor-pointer px-5 py-4 text-sm font-semibold">
                                    {t("Icon variables")}
                                </summary>
                                <CardContent className="max-h-[50dvh] space-y-4 overflow-y-auto border-t">
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            "Use an icon URL in an image src attribute.",
                                        )}
                                    </p>
                                    <div className="grid gap-3">
                                        {Object.entries(icons).map(
                                            ([name, url]) => {
                                                const snippet = `{{ '${name}' | icon_url }}`;
                                                return (
                                                    <div
                                                        key={name}
                                                        className="flex min-w-0 items-start gap-3 rounded border p-3"
                                                    >
                                                        <img
                                                            src={url}
                                                            alt=""
                                                            className="size-10 rounded bg-primary p-1"
                                                        />
                                                        <div className="min-w-0">
                                                            <code className="break-all text-xs">
                                                                {snippet}
                                                            </code>
                                                            <div className="mt-2 flex gap-2">
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        insertSnippet(
                                                                            snippet,
                                                                        )
                                                                    }
                                                                >
                                                                    {t(
                                                                        "Insert",
                                                                    )}
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        void copySnippet(
                                                                            snippet,
                                                                        )
                                                                    }
                                                                >
                                                                    {t("Copy")}
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                    {!Object.keys(icons).length && (
                                        <p className="text-sm text-muted-foreground">
                                            {t("No email icons are available.")}
                                        </p>
                                    )}
                                </CardContent>
                            </details>
                        </Card>
                    </div>
                </SettingsSection>
            </form>
            <Dialog
                open={confirm}
                onOpenChange={(open) => {
                    if (!requestPending.current) setConfirm(open);
                }}
                closeDisabled={restoring}
                title={t("Restore default")}
                description={t("This will replace the saved email template.")}
            >
                <div className="flex justify-end gap-2" aria-busy={restoring}>
                    <Button
                        variant="outline"
                        disabled={restoring}
                        onClick={() => setConfirm(false)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        loading={restoring}
                        disabled={form.processing || restoring}
                        onClick={() => {
                            if (requestPending.current || form.processing)
                                return;
                            form.post(restore, {
                                onStart: () => {
                                    requestPending.current = true;
                                    setRestoring(true);
                                },
                                onSuccess: (page) => {
                                    const restored = {
                                        ...form.data,
                                        email_content: String(
                                            page.props.content ?? "",
                                        ),
                                        email_subject: String(
                                            page.props.subject ?? "",
                                        ),
                                    };
                                    form.setDefaults(restored);
                                    form.setData({
                                        ...latestData.current,
                                        email_content:
                                            latestData.current.email_content ===
                                            form.data.email_content
                                                ? restored.email_content
                                                : latestData.current
                                                      .email_content,
                                        email_subject:
                                            latestData.current.email_subject ===
                                            form.data.email_subject
                                                ? restored.email_subject
                                                : latestData.current
                                                      .email_subject,
                                    });
                                    setConfirm(false);
                                },
                                onFinish: () => {
                                    requestPending.current = false;
                                    setRestoring(false);
                                },
                            });
                        }}
                    >
                        {t("Restore")}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
