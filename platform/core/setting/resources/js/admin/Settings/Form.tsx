import { Field } from "@admin/components/field";
import { notify } from "@admin/components/toast";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Dialog } from "@admin/components/ui/dialog";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import AdminLayout from "@admin/layouts/admin-layout";
import { adminResult, isRecord } from "@admin/lib/admin-api";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    useDraftBaseline,
    useUnsavedChanges,
} from "@admin/lib/use-unsaved-changes";
import { cn } from "@admin/lib/utils";
import { Head, Link, useForm } from "@inertiajs/react";
import axios from "axios";
import { useRef, useState } from "react";
import { ApiTokenCard } from "../components/api-token-card";
import { SettingFieldControl } from "../components/setting-field-control";
import { fieldSections, isSettingFieldVisible } from "./field-sections";
import { SettingsSectionNav, type EmailNavigation } from "./section-nav";
import { SettingsSaveFooter, SettingsSection } from "./settings-section";
export type Value =
    | string
    | number
    | boolean
    | null
    | Array<
          | string
          | number
          | { key: string; value: string }
          | Array<{ key: string; value: string }>
      >;
export type SettingField = {
    name: string;
    label: string;
    type: string;
    value: Value;
    description?: string;
    description_html?: string;
    mode?: string;
    iconsUrl?: string;
    readonly?: boolean;
    placeholder?: string;
    disabled?: boolean;
    secret?: boolean;
    stored?: boolean;
    choices: { value: string; label: string; image?: string }[];
    conditions?: { name: string; value: string }[];
    children?: { label: string; type: string; attributes: { name: string } }[];
};
export type SettingProps = {
    title: string;
    kind?: "email" | "media" | "email-template" | null;
    emailNavigation?: EmailNavigation;
    languageNavigation?: { label: string; url: string; active: boolean }[];
    media?: { list: string; upload: string };
    description?: string;
    fields: SettingField[];
    submit: string;
    extras?: { testMail?: string; thumbnails?: string; fileCount?: number };
    tokenUrl?: string;
    plainTextToken?: string;
    templates?: {
        title: string;
        code?: string;
        url: string;
        enabled: boolean;
        canOff: boolean;
        group: string;
        groupLabel: string;
        description: string;
        statusUrl: string;
        key: string;
    }[];
};
function parse(value: Value): Value {
    if (typeof value === "string") {
        try {
            return JSON.parse(value);
        } catch {
            return value;
        }
    }
    return value;
}
function settingValue(field: SettingField): Value {
    if (field.type === "checkbox")
        return [true, 1, "1"].includes(field.value as string | number | boolean)
            ? 1
            : 0;
    if (["emails", "images", "multiple", "repeater"].includes(field.type))
        return parse(field.value) || [];
    return field.value ?? "";
}
export default function SettingsForm({
    title,
    kind,
    emailNavigation,
    languageNavigation = [],
    description,
    fields,
    submit,
    extras,
    templates,
    tokenUrl,
    plainTextToken,
    media,
}: SettingProps) {
    const t = useAdminTranslation();
    const form = useForm<Record<string, Value>>(
        Object.fromEntries(
            fields
                .filter((f) => f.type !== "notice" && f.type !== "json-upload")
                .map((f) => [f.name, settingValue(f)]),
        ),
    );
    const [cleared, setCleared] = useState<string[]>([]);
    const [thumbnailProgress, setThumbnailProgress] = useState<number | null>(
        null,
    );
    const [working, setWorking] = useState(false);
    const [thumbnailOpen, setThumbnailOpen] = useState(false);
    const thumbnailTriggerRef = useRef<HTMLButtonElement>(null);
    const thumbnailCancelRef = useRef<HTMLButtonElement>(null);
    const [testEmail, setTestEmail] = useState("");
    const [testOpen, setTestOpen] = useState(false);
    const actionPending = useRef(false);
    const [testTemplate, setTestTemplate] = useState("");
    const [templateStatus, setTemplateStatus] = useState<
        Record<string, boolean>
    >({});
    const draft = useDraftBaseline(cleared);
    useUnsavedChanges(form.isDirty || draft.dirty, form.processing);
    const change = (name: string, value: Value) => form.setData(name, value);
    async function action(url: string, data: Record<string, unknown> = {}) {
        if (actionPending.current) return;
        actionPending.current = true;
        setWorking(true);
        try {
            const result = await axios.post(url, data);
            const validated = adminResult(result.data);
            notify(validated.message || t("Saved"));
            return result.data;
        } catch (error) {
            notify(
                axios.isAxiosError(error)
                    ? error.response?.data?.message || error.message
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
                true,
            );
        } finally {
            actionPending.current = false;
            setWorking(false);
        }
    }
    async function generateThumbnails() {
        if (
            actionPending.current ||
            working ||
            form.processing ||
            form.isDirty ||
            draft.dirty ||
            !extras?.thumbnails ||
            !extras.fileCount
        )
            return;
        actionPending.current = true;
        setWorking(true);
        setThumbnailProgress(0);
        try {
            let offset = 0;
            let message = "";
            while (offset < extras.fileCount) {
                const response = await axios.post(extras.thumbnails, {
                    offset,
                    limit: 10,
                    total: extras.fileCount,
                });
                const result = adminResult<{ next: number }>(
                    response.data,
                    (data) =>
                        isRecord(data) &&
                        typeof data.next === "number" &&
                        Number.isFinite(data.next),
                );
                const next = result.data.next;
                if (!Number.isFinite(next) || next <= offset)
                    throw new Error(t("Something went wrong"));
                offset = next;
                message = response.data.message;
                setThumbnailProgress(Math.min(offset, extras.fileCount));
            }
            notify(message || t("Saved"));
        } catch (error) {
            notify(
                axios.isAxiosError(error)
                    ? error.response?.data?.message || error.message
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
                true,
            );
        } finally {
            actionPending.current = false;
            setWorking(false);
        }
    }
    const submitForm = () => {
        form.transform((data) =>
            Object.fromEntries(
                Object.entries(data).filter(
                    ([name, value]) =>
                        !fields.find((f) => f.name === name)?.disabled &&
                        !(
                            fields.find((f) => f.name === name)?.secret &&
                            value === "" &&
                            !cleared.includes(name)
                        ),
                ),
            ),
        );
        form.put(submit, {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults(form.data);
                draft.saved();
            },
        });
    };
    const sections = fieldSections(fields, kind);
    const templateGroups = new Map<
        string,
        NonNullable<SettingProps["templates"]>
    >();
    for (const template of templates ?? []) {
        const group = templateGroups.get(template.group) ?? [];
        group.push(template);
        templateGroups.set(template.group, group);
    }
    const renderField = (field: SettingField) => (
        <SettingFieldControl
            key={field.name}
            field={field}
            form={form}
            kind={kind}
            media={media}
            cleared={cleared}
            setCleared={setCleared}
        />
    );
    const renderFields = (items: SettingField[]) =>
        items.map((field, index) => {
            const next = items[index + 1];
            const previous = items[index - 1];
            if (
                kind === "media" &&
                /^media_sizes_.+_height$/.test(field.name) &&
                previous?.name === field.name.replace(/_height$/, "_width")
            )
                return null;
            if (
                kind === "media" &&
                /^media_sizes_.+_width$/.test(field.name) &&
                next?.name === field.name.replace(/_width$/, "_height")
            ) {
                if (
                    ![field, next].some((item) =>
                        isSettingFieldVisible(item, form.data),
                    )
                )
                    return null;
                return (
                    <fieldset
                        key={field.name}
                        className="grid min-w-0 gap-4 sm:grid-cols-2"
                    >
                        <legend className="sr-only">
                            {field.name
                                .replace(/^media_sizes_/, "")
                                .replace(/_width$/, "")
                                .replaceAll("_", " ")}
                        </legend>
                        {renderField(field)}
                        {renderField(next)}
                    </fieldset>
                );
            }
            return renderField(field);
        });
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="space-y-6">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submitForm();
                    }}
                    className="space-y-6"
                >
                    {plainTextToken && (
                        <Card>
                            <CardContent>
                                <p className="mb-2 text-sm">
                                    {t(
                                        "Copy this token now. It will only be shown once.",
                                    )}
                                </p>
                                <code className="break-all">
                                    {plainTextToken}
                                </code>
                            </CardContent>
                        </Card>
                    )}
                    <SettingsSection
                        description={description}
                        navigation={
                            languageNavigation.length > 1 ? (
                                <nav
                                    aria-label={t("Language")}
                                    className="flex flex-wrap gap-2"
                                >
                                    {languageNavigation.map((language) => (
                                        <Button
                                            key={language.url}
                                            asChild
                                            size="sm"
                                            variant={
                                                language.active
                                                    ? "default"
                                                    : "outline"
                                            }
                                        >
                                            <Link
                                                href={language.url}
                                                aria-current={
                                                    language.active
                                                        ? "page"
                                                        : undefined
                                                }
                                            >
                                                {language.label}
                                            </Link>
                                        </Button>
                                    ))}
                                </nav>
                            ) : (
                                <SettingsSectionNav links={emailNavigation} />
                            )
                        }
                    >
                        <Card>
                            <CardContent className="space-y-8">
                                {sections.map((section) => (
                                    <div
                                        key={section.key}
                                        id={`settings-${section.key}`}
                                        className="scroll-mt-24"
                                    >
                                        {section.title &&
                                            !(
                                                section.fields.length === 1 &&
                                                section.fields[0].label.toLowerCase() ===
                                                    section.title.toLowerCase()
                                            ) && (
                                                <h2 className="mb-4 text-sm font-semibold">
                                                    {t(section.title)}
                                                </h2>
                                            )}
                                        <div className="space-y-6 divide-y [&>div]:pt-5 [&>div:first-child]:pt-0 [&>fieldset]:pt-5">
                                            {renderFields(section.fields)}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                            <SettingsSaveFooter
                                processing={form.processing}
                                dirty={form.isDirty || draft.dirty}
                                disabled={working}
                            />
                        </Card>
                        {tokenUrl && <ApiTokenCard url={tokenUrl} />}
                    </SettingsSection>
                </form>
                {extras?.testMail && (
                    <SettingsSection
                        title={t("Send a test email")}
                        description={t(
                            "Save your mail settings before sending a test email.",
                        )}
                    >
                        <Card>
                            <CardContent>
                                <Button
                                    variant="outline"
                                    disabled={
                                        working ||
                                        form.processing ||
                                        form.isDirty ||
                                        draft.dirty
                                    }
                                    onClick={() => setTestOpen(true)}
                                >
                                    {t("Send test email")}
                                </Button>
                            </CardContent>
                        </Card>
                        <Dialog
                            open={testOpen}
                            onOpenChange={(open) => {
                                if (!working) setTestOpen(open);
                            }}
                            title={t("Send a test email")}
                            description={t(
                                "Save your mail settings before sending a test email.",
                            )}
                            size="sm"
                        >
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (
                                        !testEmail ||
                                        form.isDirty ||
                                        draft.dirty ||
                                        working ||
                                        form.processing
                                    )
                                        return;
                                    void action(extras.testMail!, {
                                        email: testEmail,
                                        template: testTemplate,
                                    });
                                }}
                            >
                                <div className="grid gap-5">
                                    <Field label={t("Test email")}>
                                        {(id) => (
                                            <Input
                                                id={id}
                                                type="email"
                                                required
                                                disabled={working}
                                                value={testEmail}
                                                onChange={(e) =>
                                                    setTestEmail(e.target.value)
                                                }
                                            />
                                        )}
                                    </Field>
                                    <Field label={t("Email template")}>
                                        {(id) => (
                                            <Select
                                                id={id}
                                                disabled={working}
                                                value={testTemplate}
                                                onValueChange={(value) =>
                                                    setTestTemplate(value)
                                                }
                                            >
                                                <option value="">
                                                    {t("Default test email")}
                                                </option>
                                                {templates?.map((template) => (
                                                    <option
                                                        key={template.url}
                                                        value={template.code}
                                                    >
                                                        {template.title}
                                                    </option>
                                                ))}
                                            </Select>
                                        )}
                                    </Field>
                                </div>
                                <div className="mt-5 flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        disabled={working}
                                        onClick={() => setTestOpen(false)}
                                    >
                                        {t("Close")}
                                    </Button>
                                    <Button
                                        type="submit"
                                        loading={working}
                                        disabled={
                                            !testEmail ||
                                            form.isDirty ||
                                            draft.dirty ||
                                            working ||
                                            form.processing
                                        }
                                    >
                                        {t("Send test email")}
                                    </Button>
                                </div>
                            </form>
                        </Dialog>
                    </SettingsSection>
                )}
                {extras?.thumbnails && (
                    <SettingsSection
                        title={t("Regenerate thumbnails")}
                        description={t(
                            "Save image settings first. Existing uploads will be processed in batches.",
                        )}
                    >
                        <Card>
                            <CardContent className="flex flex-wrap items-center gap-3">
                                <p className="text-sm text-muted-foreground">
                                    {extras.fileCount ?? 0} {t("files")}
                                </p>
                                <Button
                                    ref={thumbnailTriggerRef}
                                    variant="outline"
                                    loading={working}
                                    disabled={
                                        working ||
                                        form.processing ||
                                        form.isDirty ||
                                        draft.dirty ||
                                        !extras.fileCount
                                    }
                                    onClick={() => setThumbnailOpen(true)}
                                >
                                    {t("Generate thumbnails")}
                                </Button>
                                {thumbnailProgress !== null && (
                                    <div
                                        role="status"
                                        className="w-full space-y-2 text-xs text-muted-foreground"
                                    >
                                        <progress
                                            aria-label={t(
                                                "Regenerate thumbnails",
                                            )}
                                            max={extras.fileCount || 1}
                                            value={thumbnailProgress}
                                            className="h-2 w-full accent-primary"
                                        />
                                        <p>
                                            {thumbnailProgress} /{" "}
                                            {extras.fileCount} {t("files")}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        <Dialog
                            open={thumbnailOpen}
                            onOpenChange={setThumbnailOpen}
                            title={t("Generate thumbnails")}
                            size="sm"
                            initialFocusRef={thumbnailCancelRef}
                            returnFocusRef={thumbnailTriggerRef}
                            footer={
                                <>
                                    <Button
                                        ref={thumbnailCancelRef}
                                        variant="outline"
                                        onClick={() => setThumbnailOpen(false)}
                                    >
                                        {t("Cancel")}
                                    </Button>
                                    <Button
                                        disabled={
                                            working ||
                                            form.processing ||
                                            form.isDirty ||
                                            draft.dirty ||
                                            !extras.fileCount
                                        }
                                        onClick={() => {
                                            setThumbnailOpen(false);
                                            void generateThumbnails();
                                        }}
                                    >
                                        {t("Generate")}
                                    </Button>
                                </>
                            }
                        >
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {t(
                                    "Are you sure want to re-generate thumbnails for all images? This may take some time. Please do not navigate away from this page until the process is complete.",
                                )}
                            </p>
                        </Dialog>
                    </SettingsSection>
                )}
                {templates && kind !== "email" && (
                    <SettingsSection
                        title={t("Notification templates")}
                        description={t(
                            "Edit message content and choose which notifications are enabled.",
                        )}
                    >
                        <div className="space-y-4">
                            {[...templateGroups.entries()].map(
                                ([group, groupTemplates]) => (
                                    <Card key={group}>
                                        <CardContent className="divide-y">
                                            <h2 className="pb-3 text-sm font-semibold">
                                                {groupTemplates[0].groupLabel}
                                            </h2>
                                            <div className="flex justify-between gap-4 py-3 text-xs font-medium text-muted-foreground">
                                                <span>{t("Template")}</span>
                                                <span>{t("Enabled")}</span>
                                            </div>
                                            {groupTemplates.map((template) => (
                                                <div
                                                    key={template.url}
                                                    className="flex items-center justify-between gap-4 py-3"
                                                >
                                                    <div className="min-w-0 space-y-1">
                                                        <Link
                                                            href={template.url}
                                                            className={cn(
                                                                "text-sm font-medium hover:underline",
                                                                template.canOff &&
                                                                    !(
                                                                        templateStatus[
                                                                            template
                                                                                .key
                                                                        ] ??
                                                                        template.enabled
                                                                    ) &&
                                                                    "text-muted-foreground line-through",
                                                            )}
                                                        >
                                                            {template.title}
                                                        </Link>
                                                        {template.description && (
                                                            <p className="text-sm text-muted-foreground">
                                                                {
                                                                    template.description
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <Checkbox
                                                            checked={
                                                                !template.canOff ||
                                                                (templateStatus[
                                                                    template.key
                                                                ] ??
                                                                    template.enabled)
                                                            }
                                                            disabled={
                                                                working ||
                                                                !template.canOff
                                                            }
                                                            aria-readonly={
                                                                !template.canOff
                                                            }
                                                            aria-label={`${t("Enabled")}: ${template.title}`}
                                                            onCheckedChange={async (
                                                                checked,
                                                            ) => {
                                                                if (
                                                                    !template.canOff
                                                                )
                                                                    return;
                                                                const result =
                                                                    await action(
                                                                        template.statusUrl,
                                                                        {
                                                                            key: template.key,
                                                                            value:
                                                                                checked ===
                                                                                true
                                                                                    ? 1
                                                                                    : 0,
                                                                        },
                                                                    );
                                                                if (result)
                                                                    setTemplateStatus(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            [template.key]:
                                                                                checked ===
                                                                                true,
                                                                        }),
                                                                    );
                                                            }}
                                                        />
                                                    </label>
                                                </div>
                                            ))}
                                        </CardContent>
                                    </Card>
                                ),
                            )}
                        </div>
                    </SettingsSection>
                )}
            </div>
        </AdminLayout>
    );
}
