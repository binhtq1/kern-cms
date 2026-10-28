import { GalleryEditor } from "@admin/components/gallery-editor";
import {
    PermalinkField,
    type PermalinkData,
} from "@admin/components/permalink-field";
import {
    useUnsavedChanges,
    useDraftBaseline,
} from "@admin/lib/use-unsaved-changes";
import { Card, CardTitle } from "@admin/components/ui/card";
import { Badge } from "@admin/components/ui/badge";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { ArrowLeft, Save } from "lucide-react";
import { LoadingIndicator } from "@admin/components/loading-indicator";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Input, Textarea } from "@admin/components/ui/input";
import { Field } from "@admin/components/field";
import { RichEditor, type Shortcode } from "@admin/components/rich-editor";
import { MediaPicker } from "@admin/components/media-picker";
import {
    CustomFields,
    initializeCustomFields,
    type CustomFieldGroup,
} from "@admin/components/custom-fields";
import PageRevisions, { type PageRevision } from "./PageRevisions";
import type { Option } from "@admin/types";
type PageData = {
    id?: string;
    name: string;
    description: string;
    content: string;
    template: string;
    status: string;
    image: string;
    banner_image?: string;
    slug: string;
    seo_meta: {
        seo_title: string;
        seo_description: string;
        seo_image?: string;
        index?: string;
    };
};
type Props = {
    page: PageData;
    revisions?: PageRevision[];
    galleryEnabled?: boolean;
    bannerEnabled?: boolean;
    gallery?: { img: string; description: string }[];
    templates: Option[];
    statuses: Option[];
    urls: {
        index: string;
        submit: string;
        media?: string;
        mediaUpload?: string;
    };
    method?: "post";
    shortcodes?: Shortcode[];
    customFieldGroups?: CustomFieldGroup[];
    languages?: { code: string; name: string; url: string; active: boolean }[];
    language?: string;
    translatable?: boolean;
    translatableFields?: string[];
    slugEnabled?: boolean;
    permalink?: PermalinkData | null;
    seoEnabled?: boolean;
};
export default function Form(props: Props) {
    const t = useAdminTranslation();
    return (
        <PageForm
            key={`${props.page.id ?? "new"}-${props.language ?? ""}`}
            {...props}
        />
    );
}
function PageForm({
    page,
    revisions = [],
    galleryEnabled = false,
    bannerEnabled = false,
    gallery = [],
    templates,
    statuses,
    urls,
    shortcodes,
    customFieldGroups = [],
    languages = [],
    language = "",
    translatable = false,
    translatableFields = [],
    slugEnabled = true,
    permalink,
    seoEnabled = true,
}: Props) {
    const t = useAdminTranslation();
    const [groups, setGroups] = useState(() =>
        initializeCustomFields(customFieldGroups),
    );
    const [galleryImages, setGalleryImages] = useState(gallery);
    const [schemaLoading, setSchemaLoading] = useState(false);
    const [submitter, setSubmitter] = useState("save");
    const savePending = useRef(false);
    useEffect(() => {
        setGroups((current) =>
            initializeCustomFields(customFieldGroups).map((group) => ({
                ...group,
                items: group.items.map((field) => {
                    const previous = current
                        .flatMap((item) => item.items)
                        .find((item) => String(item.id) === String(field.id));
                    return previous
                        ? { ...field, value: previous.value }
                        : field;
                }),
            })),
        );
    }, [customFieldGroups]);
    const form = useForm({
        name: page.name ?? "",
        description: page.description ?? "",
        content: page.content ?? "",
        template: page.template ?? templates[0]?.value ?? "",
        status: page.status ?? statuses[0]?.value ?? "",
        image: page.image ?? "",
        banner_image: page.banner_image ?? "",
        slug: page.slug ?? "",
        seo_meta: {
            seo_title: page.seo_meta?.seo_title ?? "",
            seo_description: page.seo_meta?.seo_description ?? "",
            index: page.seo_meta?.index ?? "index",
        },
        seo_meta_image: page.seo_meta?.seo_image ?? "",
        language: language ?? "",
        submitter: "save",
        custom_fields: "",
    });
    const draft = useDraftBaseline({ galleryImages, groups });
    useUnsavedChanges(form.isDirty || draft.dirty, form.processing);
    const title = page.id ? t("Edit page") : t("Create page");
    const error = (key: string) => (form.errors as Record<string, string>)[key];
    const editable = (key: string) =>
        !translatable ||
        !translatableFields.length ||
        translatableFields.includes(key);
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (savePending.current || form.processing || schemaLoading) return;
        const button = (event.nativeEvent as SubmitEvent)
            .submitter as HTMLButtonElement | null;
        const action = button?.value === "apply" ? "apply" : "save";
        setSubmitter(action);
        form.transform((data) => ({
            ...data,
            submitter: action,
            gallery: galleryEnabled ? JSON.stringify(galleryImages) : undefined,
            custom_fields: groups.length ? JSON.stringify(groups) : "",
            is_slug_editable: slugEnabled ? 1 : 0,
        }));
        form.post(urls.submit, {
            preserveScroll: true,
            onStart: () => {
                savePending.current = true;
            },
            onSuccess: () => {
                form.setDefaults(form.data);
                draft.saved();
            },
            onFinish: () => {
                savePending.current = false;
            },
        });
    }
    return (
        <AdminLayout
            title={title}
            description={page.id ? page.name : undefined}
            actions={
                <Button variant="outline" asChild>
                    <Link href={urls.index}>
                        <ArrowLeft size={16} />
                        {t("Back to pages")}
                    </Link>
                </Button>
            }
        >
            <Head title={title} />
            {languages.length > 0 && (
                <nav
                    aria-label={t("Content languages")}
                    className="mb-5 flex flex-wrap gap-2"
                >
                    {languages.map((item) => (
                        <Button
                            key={item.code}
                            variant={item.active ? "default" : "outline"}
                            size="sm"
                            asChild
                        >
                            <Link href={item.url}>{item.name}</Link>
                        </Button>
                    ))}
                </nav>
            )}
            <form
                id="page-editor"
                onSubmit={submit}
                className="grid min-w-0 grid-cols-1 gap-5 pb-20 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-6 lg:pb-0"
            >
                <div className="min-w-0 space-y-5">
                    <Card className="space-y-4 p-5">
                        <div className="border-b pb-3">
                            <CardTitle>{t("Page details")}</CardTitle>
                        </div>
                        <Field label={t("Name")} error={error("name")} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={form.data.name}
                                    maxLength={120}
                                    required
                                    autoFocus
                                    disabled={!editable("name")}
                                    aria-invalid={!!error("name")}
                                    onChange={(event) =>
                                        form.setData("name", event.target.value)
                                    }
                                />
                            )}
                        </Field>
                        {slugEnabled && (
                            <PermalinkField
                                value={form.data.slug}
                                name={form.data.name}
                                permalink={permalink}
                                error={error("slug")}
                                onChange={(value) =>
                                    form.setData("slug", value)
                                }
                            />
                        )}
                        <Field
                            label={t("Description")}
                            error={error("description")}
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    value={form.data.description}
                                    disabled={!editable("description")}
                                    onChange={(event) =>
                                        form.setData(
                                            "description",
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Field label={t("Content")} error={error("content")}>
                            {(id) => (
                                <RichEditor
                                    id={id}
                                    value={form.data.content}
                                    onChange={(value) =>
                                        form.setData("content", value)
                                    }
                                    mediaUrl={urls.media}
                                    uploadUrl={urls.mediaUpload}
                                    shortcodes={shortcodes}
                                />
                            )}
                        </Field>
                    </Card>
                    {seoEnabled && (
                        <Card className="space-y-4 p-5">
                            <div>
                                <h2 className="font-semibold">
                                    {t("Search engine preview")}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        "Customize how this page appears in search results.",
                                    )}
                                </p>
                            </div>
                            <div className="border-l-2 pl-4 py-1">
                                <p className="text-lg text-blue-800 dark:text-blue-300">
                                    {form.data.seo_meta.seo_title ||
                                        form.data.name ||
                                        t("Page title")}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {form.data.seo_meta.seo_description ||
                                        form.data.description ||
                                        t("Page description")}
                                </p>
                            </div>
                            <Field
                                label={t("SEO title")}
                                error={error("seo_meta.seo_title")}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={form.data.seo_meta.seo_title}
                                        maxLength={120}
                                        onChange={(event) =>
                                            form.setData("seo_meta", {
                                                ...form.data.seo_meta,
                                                seo_title: event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                label={t("SEO description")}
                                error={error("seo_meta.seo_description")}
                            >
                                {(id) => (
                                    <Textarea
                                        id={id}
                                        value={
                                            form.data.seo_meta.seo_description
                                        }
                                        maxLength={400}
                                        onChange={(event) =>
                                            form.setData("seo_meta", {
                                                ...form.data.seo_meta,
                                                seo_description:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                label={t("Search engine visibility")}
                                error={error("seo_meta.index")}
                            >
                                {(id) => (
                                    <Select
                                        id={id}

                                        value={form.data.seo_meta.index}
                                        onValueChange={(value) =>
                                            form.setData("seo_meta", {
                                                ...form.data.seo_meta,
                                                index: value,
                                            })
                                        }
                                    >
                                        <option value="index">
                                            {t(
                                                "Allow search engines to index this page",
                                            )}
                                        </option>
                                        <option value="noindex">
                                            {t(
                                                "Hide this page from search engines",
                                            )}
                                        </option>
                                    </Select>
                                )}
                            </Field>
                            <Field
                                label={t("Social sharing image")}
                                error={error("seo_meta_image")}
                            >
                                {(id) => (
                                    <MediaPicker
                                        variant="featured"
                                        id={id}
                                        value={form.data.seo_meta_image}
                                        onChange={(value) =>
                                            form.setData(
                                                "seo_meta_image",
                                                value,
                                            )
                                        }
                                        listUrl={urls.media}
                                        uploadUrl={urls.mediaUpload}
                                    />
                                )}
                            </Field>
                        </Card>
                    )}
                    <CustomFields
                        groups={groups}
                        onChange={setGroups}
                        mediaUrl={urls.media}
                        uploadUrl={urls.mediaUpload}
                    />
                    {error("custom_fields") && (
                        <p role="alert" className="text-sm text-destructive">
                            {error("custom_fields")}
                        </p>
                    )}
                    <PageRevisions revisions={revisions} />
                    {bannerEnabled && !translatable && (
                        <Card className="space-y-4 p-5">
                            <Field
                                label={t("Banner image (1920x170px)")}
                                error={error("banner_image")}
                            >
                                {(id) => (
                                    <MediaPicker
                                        variant="featured"
                                        id={id}
                                        aspect="banner"
                                        value={form.data.banner_image}
                                        onChange={(value) =>
                                            form.setData("banner_image", value)
                                        }
                                        listUrl={urls.media}
                                        uploadUrl={urls.mediaUpload}
                                    />
                                )}
                            </Field>
                        </Card>
                    )}

                    {galleryEnabled && (
                        <Card className="p-5">
                            <GalleryEditor
                                images={galleryImages}
                                onChange={setGalleryImages}
                                listUrl={urls.media}
                                uploadUrl={urls.mediaUpload}
                                error={error("gallery")}
                            />
                        </Card>
                    )}
                </div>

                <aside className="min-w-0 space-y-5 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:self-start lg:overflow-y-auto lg:pb-1">
                    <Card className="divide-y">
                        <section className="space-y-4 p-4">
                            <div className="flex items-center justify-between gap-2 border-b pb-4">
                                <CardTitle>{t("Publish")}</CardTitle>
                                {(form.isDirty || draft.dirty) && (
                                    <Badge variant="warning">
                                        {t("Unsaved")}
                                    </Badge>
                                )}
                            </div>
                            <Field label={t("Status")} error={error("status")}>
                                {(id) => (
                                    <Select
                                        id={id}

                                        value={form.data.status}
                                        disabled={!editable("status")}
                                        onValueChange={(value) =>
                                            form.setData("status", value)
                                        }
                                    >
                                        {statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </Select>
                                )}
                            </Field>
                            <div className="space-y-2 border-t pt-4">
                                <Button
                                    type="submit"
                                    value="save"
                                    disabled={form.processing || schemaLoading}
                                    loading={
                                        form.processing && submitter === "save"
                                    }
                                    className="w-full"
                                >
                                    {!(
                                        form.processing && submitter === "save"
                                    ) && <Save size={16} />}
                                    {t("Save & return")}
                                </Button>
                                <Button
                                    type="submit"
                                    value="apply"
                                    disabled={form.processing || schemaLoading}
                                    loading={
                                        form.processing && submitter === "apply"
                                    }
                                    variant="outline"
                                    className="w-full"
                                >
                                    {t("Save & continue editing")}
                                </Button>
                            </div>
                            {(form.isDirty || draft.dirty) && (
                                <p className="text-xs text-muted-foreground">
                                    {t("You have unsaved changes.")}
                                </p>
                            )}
                        </section>
                        {templates.length > 0 && (
                            <section className="p-4">
                                <Field
                                    label={t("Template")}
                                    error={error("template")}
                                >
                                    {(id) => (
                                        <Select
                                            id={id}

                                            value={form.data.template}
                                            disabled={!editable("template")}
                                            onValueChange={(value) => {
                                                const template = value;
                                                form.setData(
                                                    "template",
                                                    template,
                                                );
                                                setSchemaLoading(true);
                                                router.reload({
                                                    data: { template },
                                                    only: ["customFieldGroups"],
                                                    onFinish: () =>
                                                        setSchemaLoading(false),
                                                });
                                            }}
                                        >
                                            {templates.map((template) => (
                                                <option
                                                    key={template.value}
                                                    value={template.value}
                                                >
                                                    {template.label}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </Field>
                                {schemaLoading && (
                                    <LoadingIndicator
                                        label={t("Loading fields…")}
                                        className="mt-3"
                                        iconClassName="size-3.5"
                                    />
                                )}
                            </section>
                        )}
                        {editable("image") && (
                            <section className="p-4">
                                <Field
                                    label={t("Featured image")}
                                    error={error("image")}
                                >
                                    {(id) => (
                                        <MediaPicker
                                            variant="featured"
                                            id={id}
                                            value={form.data.image}
                                            onChange={(value) =>
                                                form.setData("image", value)
                                            }
                                            listUrl={urls.media}
                                            uploadUrl={urls.mediaUpload}
                                        />
                                    )}
                                </Field>
                            </section>
                        )}
                    </Card>
                    {Object.keys(form.errors).length > 0 && (
                        <div
                            role="alert"
                            className="rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/50 p-4 text-sm text-destructive"
                        >
                            {t("Please review the errors before saving.")}
                            <ul className="mt-2 list-disc pl-4">
                                {Object.entries(form.errors).map(
                                    ([key, message]) => (
                                        <li key={key}>{String(message)}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}
                </aside>
                <div className="fixed inset-x-0 bottom-0 z-20 flex items-center justify-end gap-2 border-t bg-card px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:left-[var(--admin-sidebar-width)] lg:hidden">
                    <Button
                        type="submit"
                        value="apply"
                        variant="outline"
                        disabled={form.processing || schemaLoading}
                        loading={form.processing && submitter === "apply"}
                    >
                        {t("Save & continue")}
                    </Button>
                    <Button
                        type="submit"
                        value="save"
                        loading={form.processing && submitter === "save"}
                        disabled={form.processing || schemaLoading}
                    >
                        {!(form.processing && submitter === "save") && (
                            <Save aria-hidden="true" />
                        )}
                        {t("Save & return")}
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
