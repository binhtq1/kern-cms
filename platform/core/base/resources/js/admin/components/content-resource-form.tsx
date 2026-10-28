import AdminLayout from "@admin/layouts/admin-layout";
import { contactLink } from "@admin/lib/contact-link";
import { completedContactReply } from "@admin/lib/contact-reply";
import {
    contentOptionIdentity,
    moveContentOption,
    updateContentOption,
    type ContentFieldOption,
} from "@admin/lib/content-option-state";
import { useAdminDate } from "@admin/lib/date";
import { parseTags } from "@admin/lib/tags";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import {
    useDraftBaseline,
    useUnsavedChanges,
} from "@admin/lib/use-unsaved-changes";
import type { Option, SharedProps } from "@admin/types";
import { Head, Link, router, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    GripVertical,
    Plus,
    Trash2,
} from "lucide-react";
import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { ContentSchemaField } from "./content-schema-field";
import {
    CustomFields,
    initializeCustomFields,
    type CustomFieldGroup,
} from "./custom-fields";
import { Field } from "./field";
import { GalleryEditor } from "./gallery-editor";
import { MediaPicker } from "./media-picker";
import { PermalinkField, type PermalinkData } from "./permalink-field";
import { RichEditor, type Shortcode } from "./rich-editor";
import { SafeRichText } from "./safe-rich-text";
import { notify } from "./toast";
import { Button } from "./ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "./ui/card";
import { Input } from "./ui/input";
import { Select } from "./ui/select";

export type Value =
    string | number | boolean | string[] | Record<string, string>;
export type SchemaField = {
    name: string;
    label: string;
    type: string;
    required: boolean;
    readonly: boolean;
    choices: Option[];
    maxLength?: number;
    description?: string;
    description_html?: string;
    placeholder?: string;
    mode?: string;
    iconsUrl?: string;
};
type GalleryImage = { img: string; description: string };
type FieldOption = ContentFieldOption;
export type ContentFormProps = {
    title: string;
    resource: string;
    id?: string | null;
    values: Record<string, Value>;
    fields: SchemaField[];
    language?: string;
    languages: { name: string; active: boolean; url: string }[];
    translatable: boolean;
    slugEnabled: boolean;
    permalink?: PermalinkData | null;
    seoEnabled: boolean;
    galleryEnabled: boolean;
    gallery: GalleryImage[];
    customFieldGroups: CustomFieldGroup[];
    shortcodes: Shortcode[];
    permissions: { create: boolean; edit: boolean };
    fieldOptions: FieldOption[];
    contact?: Record<string, unknown>;
    replies: { id: string; message: string; created_at: string }[];
    urls: {
        index: string;
        submit: string;
        media: string;
        mediaUpload: string;
        reply?: string;
        verify?: string;
    };
};
export function ContentResourceForm(
    props: ContentFormProps & {
        embedded?: boolean;
        onSaved?: (form: ContentFormProps, reset: boolean) => void;
        onDirtyChange?: (dirty: boolean) => void;
        onBusyChange?: (busy: boolean) => void;
        onNavigate?: (url: string) => void;
    },
) {
    const { resource, id, fields, urls, permissions } = props;
    const resourceLabels: Record<string, string> = {
        posts: "Post",
        categories: "Category",
        tags: "Tag",
        blocks: "Block",
        galleries: "Gallery",
        members: "Member",
        contacts: "Contact",
        "contact-fields": "Custom field",
    };
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    const title = `${t(id ? "Edit" : "Create")} ${t(resourceLabels[resource] ?? props.title)}`;
    const form = useForm<Record<string, Value>>(props.values);
    const reply = useForm({ message: "" });
    const replyDraft = useRef(reply.data.message);
    replyDraft.current = reply.data.message;
    const [gallery, setGallery] = useState(props.gallery ?? []);
    const [groups, setGroups] = useState(() =>
        initializeCustomFields(props.customFieldGroups ?? []),
    );
    const [options, setOptions] = useState(props.fieldOptions ?? []);
    const optionScope = useId();
    const optionTarget = (option: FieldOption, after = false) =>
        JSON.stringify([optionScope, contentOptionIdentity(option), after]);
    function moveOption(from: number, to: number) {
        if (props.translatable || !canSave || from === to) return;
        setOptions((current) => moveContentOption(current, from, to));
    }
    const optionSort = usePointerSort((key, destination) => {
        let parsed: unknown;
        try {
            parsed = JSON.parse(destination);
        } catch {
            return;
        }
        if (!Array.isArray(parsed)) return;
        const [scope, target, after] = parsed as [string, string, boolean];
        if (scope !== optionScope) return;
        const from = options.findIndex(
            (option) => contentOptionIdentity(option) === key,
        );
        const targetIndex = options.findIndex(
            (option) => contentOptionIdentity(option) === target,
        );
        if (from < 0 || targetIndex < 0) return;
        const insertion = targetIndex + (after ? 1 : 0);
        moveOption(from, insertion > from ? insertion - 1 : insertion);
    });
    const [submitter, setSubmitter] = useState("save");
    const savePending = useRef(false);
    const draft = useDraftBaseline({ gallery, groups, options });
    useUnsavedChanges(
        !props.embedded && (form.isDirty || draft.dirty || reply.isDirty),
        form.processing || reply.processing,
    );
    const [embeddedBusy, setEmbeddedBusy] = useState(false);
    const processing = form.processing || embeddedBusy;
    const { onDirtyChange, onBusyChange } = props;
    useEffect(() => {
        onDirtyChange?.(form.isDirty || draft.dirty);
    }, [form.isDirty, draft.dirty, onDirtyChange]);
    useEffect(() => {
        onBusyChange?.(processing);
    }, [processing, onBusyChange]);
    const canSave = id ? permissions.edit : permissions.create;
    const changingPassword =
        !id || Boolean(Number(form.data.is_change_password));
    const text = (name: string) => String(form.data[name] ?? "");
    const seo = (form.data.seo_meta ?? {}) as Record<string, string>;
    function payload(data: Record<string, Value>, action: string) {
        return {
            ...data,
            _method: "POST",
            submitter: action,
            language: props.language ?? "",
            ...(props.galleryEnabled
                ? { gallery: JSON.stringify(gallery) }
                : {}),
            ...(groups.length ? { custom_fields: JSON.stringify(groups) } : {}),
            ...(resource === "contact-fields"
                ? {
                      options: options.map((option, order) => ({
                          ...option,
                          order,
                      })),
                  }
                : {}),
            ...(resource === "posts"
                ? {
                      tag: JSON.stringify(
                          parseTags(text("tag")).map((value) => ({ value })),
                      ),
                  }
                : {}),
        };
    }
    async function save(event: FormEvent) {
        event.preventDefault();
        if (savePending.current || processing || !canSave) return;
        const button = (event.nativeEvent as SubmitEvent)
            .submitter as HTMLButtonElement | null;
        const action = button?.value === "apply" ? "apply" : "save";
        setSubmitter(action);
        if (props.embedded) {
            savePending.current = true;
            setEmbeddedBusy(true);
            form.clearErrors();
            try {
                const response = await axios.post(
                    urls.submit,
                    payload(form.data, action),
                    {
                        params: { embedded: "1" },
                        headers: { Accept: "application/json" },
                    },
                );
                if (response.data.error) throw new Error(response.data.message);
                form.setDefaults(form.data);
                draft.saved();
                notify(response.data.message || t("Changes saved"));
                props.onDirtyChange?.(false);
                props.onSaved?.(response.data.data.form, action === "save");
            } catch (error) {
                if (
                    axios.isAxiosError(error) &&
                    error.response?.status === 422
                ) {
                    const errors = error.response.data.errors ?? {};
                    form.setError(
                        Object.fromEntries(
                            Object.entries(errors).map(([name, messages]) => [
                                name,
                                Array.isArray(messages)
                                    ? String(messages[0])
                                    : String(messages),
                            ]),
                        ),
                    );
                } else {
                    notify(
                        axios.isAxiosError(error)
                            ? error.response?.data?.message || error.message
                            : error instanceof Error
                              ? error.message
                              : t("Something went wrong"),
                        true,
                    );
                }
            } finally {
                savePending.current = false;
                setEmbeddedBusy(false);
            }
            return;
        }
        form.transform((data) => payload(data, action));
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
    const renderField = (field: SchemaField) => (
        <ContentSchemaField
            key={field.name}
            field={field}
            id={id}
            changingPassword={changingPassword}
            canSave={canSave}
            embeddedBusy={embeddedBusy}
            form={form}
            urls={urls}
            shortcodes={props.shortcodes}
        />
    );
    const mainFields = fields.filter(
        (field) =>
            ![
                "status",
                "image",
                "avatar_image",
                "categories",
                "tag",
                "format_type",
            ].includes(field.name),
    );
    const sideFields = fields.filter((field) =>
        [
            "status",
            "image",
            "avatar_image",
            "categories",
            "tag",
            "format_type",
        ].includes(field.name),
    );
    const hasUnsavedChanges = form.isDirty || draft.dirty;
    const hasFormErrors = Object.keys(form.errors).length > 0;
    const content = (
        <>
            {!props.embedded && <Head title={title} />}
            {!!props.languages.length && (
                <nav
                    aria-label={t("Language")}
                    className="mb-5 flex flex-wrap gap-2"
                >
                    {props.languages.map((language) => (
                        <Button
                            key={language.url}
                            asChild={!props.embedded}
                            variant={language.active ? "default" : "outline"}
                            size="sm"
                            disabled={embeddedBusy}
                            onClick={
                                props.embedded
                                    ? () => props.onNavigate?.(language.url)
                                    : undefined
                            }
                        >
                            {props.embedded ? (
                                language.name
                            ) : (
                                <Link href={language.url}>{language.name}</Link>
                            )}
                        </Button>
                    ))}
                </nav>
            )}
            {props.translatable && (
                <p className="mb-5 text-sm text-muted-foreground">
                    {t(
                        "Only translated fields can be changed in this language.",
                    )}
                </p>
            )}
            <form
                onSubmit={save}
                className={
                    props.embedded
                        ? "grid min-w-0 gap-5"
                        : "grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]"
                }
            >
                <div className="min-w-0 space-y-5">
                    {props.contact && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t("Contact information")}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    {Object.entries(props.contact)
                                        .filter(
                                            ([key]) => key !== "custom_fields",
                                        )
                                        .map(([key, value]) => (
                                            <div key={key}>
                                                <dt className="mb-1 font-medium text-muted-foreground">
                                                    {t(
                                                        key === "created_at"
                                                            ? "Created"
                                                            : key
                                                                  .charAt(0)
                                                                  .toUpperCase() +
                                                                  key.slice(1),
                                                    )}
                                                </dt>
                                                <dd className="whitespace-pre-wrap break-words">
                                                    {key === "created_at" &&
                                                    value ? (
                                                        formatDate(
                                                            String(value),
                                                            true,
                                                        )
                                                    ) : contactLink(
                                                          key,
                                                          String(value ?? ""),
                                                      ) ? (
                                                        <a
                                                            className="text-primary underline underline-offset-2"
                                                            href={contactLink(
                                                                key,
                                                                String(value),
                                                            )!}
                                                        >
                                                            {String(value)}
                                                        </a>
                                                    ) : (
                                                        String(value ?? "")
                                                    )}
                                                </dd>
                                            </div>
                                        ))}
                                    {Object.entries(
                                        (props.contact.custom_fields ??
                                            {}) as Record<string, unknown>,
                                    ).map(([key, value]) => (
                                        <div key={key}>
                                            <dt className="font-medium">
                                                {key}
                                            </dt>
                                            <dd>{String(value)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </CardContent>
                        </Card>
                    )}
                    {!!mainFields.length && (
                        <Card>
                            <CardContent className="space-y-5">
                                {mainFields.map(renderField)}
                            </CardContent>
                        </Card>
                    )}
                    {resource === "contact-fields" &&
                        ["dropdown", "radio"].includes(text("type")) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t("Options")}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {options.map((option, index) => (
                                        <div
                                            key={contentOptionIdentity(option)}
                                            data-sort-before={optionTarget(
                                                option,
                                            )}
                                            data-sort-after={optionTarget(
                                                option,
                                                true,
                                            )}
                                            className={`relative flex flex-wrap items-start gap-2 rounded-md border p-3 ${optionSort.active === contentOptionIdentity(option) ? "opacity-40" : ""} ${optionSort.target === optionTarget(option) ? "border-t-primary" : ""} ${optionSort.target === optionTarget(option, true) ? "border-b-primary" : ""}`}
                                        >
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={t(
                                                    "Drag to reorder",
                                                )}
                                                disabled={
                                                    props.translatable ||
                                                    !canSave
                                                }
                                                {...optionSort.handle(
                                                    contentOptionIdentity(
                                                        option,
                                                    ),
                                                    option.label ||
                                                        t("Options"),
                                                )}
                                            >
                                                <GripVertical />
                                            </Button>
                                            <Field
                                                label={t("Label")}
                                                className="min-w-32 flex-1"
                                                error={
                                                    form.errors[
                                                        `options.${index}.label`
                                                    ]
                                                }
                                            >
                                                {(controlId) => (
                                                    <Input
                                                        id={controlId}
                                                        value={option.label}
                                                        disabled={!canSave}
                                                        onChange={(event) =>
                                                            setOptions(
                                                                options.map(
                                                                    (
                                                                        item,
                                                                        i,
                                                                    ) =>
                                                                        i ===
                                                                        index
                                                                            ? updateContentOption(
                                                                                  item,
                                                                                  {
                                                                                      label: event
                                                                                          .target
                                                                                          .value,
                                                                                  },
                                                                              )
                                                                            : item,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                )}
                                            </Field>
                                            <Field
                                                label={t("Value")}
                                                className="min-w-32 flex-1"
                                                error={
                                                    form.errors[
                                                        `options.${index}.value`
                                                    ]
                                                }
                                            >
                                                {(controlId) => (
                                                    <Input
                                                        id={controlId}
                                                        value={option.value}
                                                        disabled={!canSave}
                                                        onChange={(event) =>
                                                            setOptions(
                                                                options.map(
                                                                    (
                                                                        item,
                                                                        i,
                                                                    ) =>
                                                                        i ===
                                                                        index
                                                                            ? updateContentOption(
                                                                                  item,
                                                                                  {
                                                                                      value: event
                                                                                          .target
                                                                                          .value,
                                                                                  },
                                                                              )
                                                                            : item,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                )}
                                            </Field>
                                            <div className="flex gap-1 pt-7">
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={t("Move up")}
                                                    disabled={
                                                        props.translatable ||
                                                        !canSave ||
                                                        index === 0
                                                    }
                                                    onClick={() =>
                                                        moveOption(
                                                            index,
                                                            index - 1,
                                                        )
                                                    }
                                                >
                                                    <ArrowUp />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={t("Move down")}
                                                    disabled={
                                                        props.translatable ||
                                                        !canSave ||
                                                        index ===
                                                            options.length - 1
                                                    }
                                                    onClick={() =>
                                                        moveOption(
                                                            index,
                                                            index + 1,
                                                        )
                                                    }
                                                >
                                                    <ArrowDown />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={t("Remove")}
                                                    disabled={
                                                        props.translatable ||
                                                        !canSave
                                                    }
                                                    onClick={() =>
                                                        setOptions(
                                                            options.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                    {optionSort.overlay}
                                    {form.errors.options && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {form.errors.options}
                                        </p>
                                    )}
                                    {!props.translatable && (
                                        <Button
                                            variant="outline"
                                            disabled={!canSave}
                                            onClick={() =>
                                                setOptions([
                                                    ...options,
                                                    {
                                                        label: "",
                                                        value: "",
                                                        order: options.length,
                                                    },
                                                ])
                                            }
                                        >
                                            <Plus />
                                            {t("Add option")}
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    {props.galleryEnabled && (
                        <Card className="p-5">
                            <GalleryEditor
                                images={gallery}
                                onChange={setGallery}
                                listUrl={urls.media}
                                uploadUrl={urls.mediaUpload}
                                disabled={!canSave}
                                error={form.errors.gallery}
                            />
                        </Card>
                    )}
                    {props.seoEnabled && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t("Search engine optimization")}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {["seo_title", "seo_description"].map((key) => (
                                    <Field
                                        key={key}
                                        label={t(
                                            key === "seo_title"
                                                ? "SEO title"
                                                : "SEO description",
                                        )}
                                        error={form.errors[`seo_meta.${key}`]}
                                    >
                                        {(controlId) => (
                                            <Input
                                                id={controlId}
                                                value={seo[key] ?? ""}
                                                onChange={(event) =>
                                                    form.setData("seo_meta", {
                                                        ...seo,
                                                        [key]: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                        )}
                                    </Field>
                                ))}
                                <Field label={t("SEO image")}>
                                    {(controlId) => (
                                        <MediaPicker
                                            variant="featured"
                                            id={controlId}
                                            value={seo.seo_image ?? ""}
                                            listUrl={urls.media}
                                            uploadUrl={urls.mediaUpload}
                                            onChange={(url) =>
                                                form.setData("seo_meta", {
                                                    ...seo,
                                                    seo_image: url,
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field label={t("Search engine indexing")}>
                                    {(controlId) => (
                                        <Select
                                            id={controlId}
                                            value={seo.index ?? "index"}
                                            onValueChange={(value) =>
                                                form.setData("seo_meta", {
                                                    ...seo,
                                                    index: value,
                                                })
                                            }
                                        >
                                            <option value="index">
                                                {t("Index")}
                                            </option>
                                            <option value="noindex">
                                                {t("No index")}
                                            </option>
                                        </Select>
                                    )}
                                </Field>
                            </CardContent>
                        </Card>
                    )}
                    <CustomFields
                        groups={groups}
                        onChange={setGroups}
                        mediaUrl={urls.media}
                        uploadUrl={urls.mediaUpload}
                    />
                    {form.errors.custom_fields && (
                        <p role="alert" className="text-sm text-destructive">
                            {form.errors.custom_fields}
                        </p>
                    )}
                </div>
                <div className="space-y-5 lg:self-stretch">
                    {sideFields.map((field) => (
                        <Card key={field.name}>
                            <CardContent>{renderField(field)}</CardContent>
                        </Card>
                    ))}
                    {props.slugEnabled && (
                        <Card>
                            <CardContent>
                                <PermalinkField
                                    value={text("slug")}
                                    name={text("name")}
                                    permalink={props.permalink}
                                    error={form.errors.slug}
                                    disabled={!canSave}
                                    onChange={(value) =>
                                        form.setData("slug", value)
                                    }
                                />
                            </CardContent>
                        </Card>
                    )}
                    {urls.verify && (
                        <Card>
                            <CardContent>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    disabled={!permissions.edit}
                                    onClick={() => router.post(urls.verify!)}
                                >
                                    {t("Verify email")}
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                    {(canSave || hasUnsavedChanges || hasFormErrors) && (
                        <Card
                            className={
                                props.embedded
                                    ? undefined
                                    : "lg:sticky lg:top-24"
                            }
                        >
                            <CardContent className="space-y-3">
                                {canSave && (
                                    <div className="grid gap-2">
                                        <Button
                                            type="submit"
                                            value="save"
                                            loading={
                                                processing &&
                                                submitter === "save"
                                            }
                                            disabled={processing}
                                        >
                                            {t("Save")}
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            value="apply"
                                            loading={
                                                processing &&
                                                submitter === "apply"
                                            }
                                            disabled={processing}
                                        >
                                            {t("Save and continue")}
                                        </Button>
                                    </div>
                                )}
                                {hasUnsavedChanges && (
                                    <p
                                        className="text-xs text-muted-foreground"
                                        role="status"
                                    >
                                        {t("Unsaved changes")}
                                    </p>
                                )}
                                {hasFormErrors && (
                                    <div
                                        role="alert"
                                        className="space-y-2 text-sm text-destructive"
                                    >
                                        <p>
                                            {t(
                                                "Please check the highlighted fields.",
                                            )}
                                        </p>
                                        <ul className="list-disc space-y-1 pl-5">
                                            {[
                                                ...new Set(
                                                    Object.values(
                                                        form.errors,
                                                    ).filter(Boolean),
                                                ),
                                            ].map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </form>
            {resource === "contacts" && (
                <Card className="mt-5">
                    <CardHeader>
                        <CardTitle>{t("Replies")}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {props.replies.map((item) => (
                            <article key={item.id} className="border-b pb-4">
                                <time
                                    dateTime={item.created_at}
                                    className="text-xs text-muted-foreground"
                                >
                                    {formatDate(item.created_at, true)}
                                </time>
                                <SafeRichText
                                    html={item.message}
                                    className="mt-2"
                                />
                            </article>
                        ))}
                        {permissions.edit && urls.reply && (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    reply.post(urls.reply!, {
                                        preserveScroll: true,
                                        onSuccess: (page) => {
                                            const completed =
                                                completedContactReply(
                                                    page.props.flash as
                                                        | SharedProps["flash"]
                                                        | undefined,
                                                    reply.data.message,
                                                    replyDraft.current,
                                                );
                                            if (!completed) return;
                                            reply.setDefaults({ message: "" });
                                            reply.setData(
                                                "message",
                                                completed.message,
                                            );
                                        },
                                    });
                                }}
                            >
                                <Field
                                    label={t("Reply")}
                                    error={reply.errors.message}
                                    required
                                >
                                    {(controlId) => (
                                        <RichEditor
                                            id={controlId}
                                            value={reply.data.message}
                                            onChange={(message) =>
                                                reply.setData(
                                                    "message",
                                                    message,
                                                )
                                            }
                                            mediaUrl={urls.media}
                                            uploadUrl={urls.mediaUpload}
                                        />
                                    )}
                                </Field>
                                <Button
                                    className="mt-3"
                                    type="submit"
                                    loading={reply.processing}
                                >
                                    {t("Send reply")}
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
            )}
        </>
    );
    if (props.embedded)
        return (
            <section aria-label={title} className="min-w-0">
                <fieldset disabled={embeddedBusy} className="min-w-0">
                    {content}
                </fieldset>
            </section>
        );
    return (
        <AdminLayout
            title={title}
            description={
                id ? String(form.data.name ?? form.data.title ?? "") : undefined
            }
            actions={
                <Button asChild variant="outline">
                    <Link href={urls.index}>
                        <ArrowLeft />
                        {t("Back")}
                    </Link>
                </Button>
            }
        >
            {content}
        </AdminLayout>
    );
}
