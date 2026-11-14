import { Link, router, useForm } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import { ContentSchemaField } from "@admin/components/content-schema-field";
import type {
    ContentFormProps,
    Value,
} from "@admin/components/content-resource-form";
import {
    CustomFields,
    initializeCustomFields,
} from "@admin/components/custom-fields";
import { GalleryEditor } from "@admin/components/gallery-editor";
import { PermalinkField } from "@admin/components/permalink-field";
import { Field } from "@admin/components/field";
import { MediaPicker } from "@admin/components/media-picker";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { parseTags } from "@admin/lib/tags";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    MemberLayout,
    MemberErrors,
    useMember,
    useMemberDraft,
    useMemberMutation,
} from "./shared";

export default function PostForm(props: ContentFormProps) {
    const { urls: memberUrls } = useMember();
    const t = useAdminTranslation();
    const form = useForm<Record<string, Value>>(props.values);
    const mutation = useMemberMutation();
    const uploads = useMemberMutation();
    const [gallery, setGallery] = useState(props.gallery ?? []);
    const [groups, setGroups] = useState(() =>
        initializeCustomFields(props.customFieldGroups ?? []),
    );
    const snapshot = JSON.stringify({ values: form.data, gallery, groups });
    const [baseline, setBaseline] = useState(snapshot);
    const [action, setAction] = useState("save");
    const dirty = snapshot !== baseline;
    useMemberDraft(dirty, mutation.busy);
    const seo = (form.data.seo_meta ?? {}) as Record<string, string>;
    const text = (name: string) => String(form.data[name] ?? "");
    async function save(event: FormEvent) {
        event.preventDefault();
        if (mutation.busy || uploads.busy) return;
        const submitter = (event.nativeEvent as SubmitEvent)
            .submitter as HTMLButtonElement | null;
        const action = submitter?.value === "apply" ? "apply" : "save";
        setAction(action);
        const submittedSnapshot = snapshot;
        const payload = {
            ...form.data,
            submitter: action,
            _method: "POST",
            tag: JSON.stringify(
                parseTags(text("tag")).map((value) => ({ value })),
            ),
            ...(props.galleryEnabled
                ? { gallery: JSON.stringify(gallery) }
                : {}),
            ...(groups.length ? { custom_fields: JSON.stringify(groups) } : {}),
        };
        form.clearErrors();
        await mutation.run(props.urls.submit, payload, "POST", (result) => {
            setBaseline(submittedSnapshot);
            form.setDefaults(form.data);
            const destination =
                action === "apply"
                    ? result.data?.redirect_url ||
                      (props.id ? window.location.href : undefined)
                    : memberUrls.posts;
            if (destination)
                router.visit(destination, {
                    preserveScroll: action === "apply",
                });
        });
    }
    // Expose server-side field errors through the shared accessible schema controls.
    const fieldsForm = { ...form, errors: mutation.errors };
    return (
        <MemberLayout
            title={t(props.id ? "Edit post" : "Create post")}
            actions={
                <Button asChild variant="outline">
                    <Link href={memberUrls.posts!}>{t("Back")}</Link>
                </Button>
            }
        >
            <form onSubmit={save} className="space-y-5">
                <fieldset
                    disabled={mutation.busy}
                    className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <div className="space-y-5">
                        <Card>
                            <CardContent className="space-y-5">
                                {props.fields
                                    .filter(
                                        (field) =>
                                            ![
                                                "author_id",
                                                "author_type",
                                                "status",
                                                "is_featured",
                                            ].includes(field.name),
                                    )
                                    .map((field) => (
                                        <ContentSchemaField
                                            key={field.name}
                                            field={field}
                                            id={props.id}
                                            changingPassword={false}
                                            canSave
                                            embeddedBusy={mutation.busy}
                                            form={fieldsForm}
                                            urls={props.urls}
                                            shortcodes={props.shortcodes}
                                        />
                                    ))}
                            </CardContent>
                        </Card>
                        {props.galleryEnabled && (
                            <Card>
                                <CardContent className="space-y-4">
                                    <GalleryEditor
                                        images={gallery}
                                        onChange={setGallery}
                                        uploadUrl={memberUrls.upload}
                                        disabled={mutation.busy || uploads.busy}
                                    />
                                    <Field
                                        label={t("Add gallery images")}
                                        error={uploads.errors._request}
                                    >
                                        {(id) => (
                                            <Input
                                                id={id}
                                                type="file"
                                                accept="image/*"
                                                multiple
                                                disabled={
                                                    uploads.busy ||
                                                    mutation.busy
                                                }
                                                onChange={async (event) => {
                                                    const files = [
                                                        ...(event.target
                                                            .files ?? []),
                                                    ];
                                                    event.target.value = "";
                                                    for (const file of files) {
                                                        const body =
                                                            new FormData();
                                                        body.append(
                                                            "file",
                                                            file,
                                                        );
                                                        await uploads.run(
                                                            memberUrls.upload,
                                                            body,
                                                            "POST",
                                                            (result) => {
                                                                const image =
                                                                    result.data
                                                                        ?.url;
                                                                if (
                                                                    typeof image ===
                                                                    "string"
                                                                )
                                                                    setGallery(
                                                                        (
                                                                            current,
                                                                        ) => [
                                                                            ...current,
                                                                            {
                                                                                img: image,
                                                                                description:
                                                                                    "",
                                                                            },
                                                                        ],
                                                                    );
                                                            },
                                                        );
                                                    }
                                                }}
                                            />
                                        )}
                                    </Field>
                                </CardContent>
                            </Card>
                        )}
                        <div inert={mutation.busy || undefined}>
                            <CustomFields
                                groups={groups}
                                onChange={setGroups}
                                uploadUrl={memberUrls.upload}
                            />
                        </div>
                    </div>
                    <div className="space-y-5">
                        {props.slugEnabled && (
                            <Card>
                                <CardContent>
                                    <PermalinkField
                                        value={text("slug")}
                                        name={text("name")}
                                        permalink={props.permalink}
                                        error={mutation.errors.slug}
                                        disabled={mutation.busy}
                                        onChange={(value) =>
                                            form.setData("slug", value)
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}
                        {props.seoEnabled && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t("SEO")}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <Field
                                        label={t("SEO title")}
                                        error={
                                            mutation.errors[
                                                "seo_meta.seo_title"
                                            ]
                                        }
                                    >
                                        {(id) => (
                                            <Input
                                                id={id}
                                                value={seo.seo_title ?? ""}
                                                maxLength={250}
                                                onChange={(event) =>
                                                    form.setData("seo_meta", {
                                                        ...seo,
                                                        seo_title:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        )}
                                    </Field>
                                    <Field
                                        label={t("SEO description")}
                                        error={
                                            mutation.errors[
                                                "seo_meta.seo_description"
                                            ]
                                        }
                                    >
                                        {(id) => (
                                            <Textarea
                                                id={id}
                                                value={
                                                    seo.seo_description ?? ""
                                                }
                                                maxLength={1000}
                                                onChange={(event) =>
                                                    form.setData("seo_meta", {
                                                        ...seo,
                                                        seo_description:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        )}
                                    </Field>
                                    <Field
                                        label={t("SEO image")}
                                        error={
                                            mutation.errors[
                                                "seo_meta.seo_image"
                                            ]
                                        }
                                    >
                                        {(id) => (
                                            <MediaPicker
                                                id={id}
                                                value={seo.seo_image ?? ""}
                                                uploadUrl={memberUrls.upload}
                                                onChange={(value) =>
                                                    form.setData("seo_meta", {
                                                        ...seo,
                                                        seo_image: value,
                                                    })
                                                }
                                            />
                                        )}
                                    </Field>
                                    <Field label={t("Search engine indexing")}>
                                        {(id) => (
                                            <Select
                                                id={id}
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
                        <Card className="lg:sticky lg:top-5">
                            <CardContent className="space-y-3">
                                <MemberErrors errors={mutation.errors} />
                                <Button
                                    type="submit"
                                    value="save"
                                    className="w-full"
                                    loading={mutation.busy && action === "save"}
                                    disabled={mutation.busy || uploads.busy}
                                >
                                    {t("Save")}
                                </Button>
                                <Button
                                    type="submit"
                                    value="apply"
                                    variant="outline"
                                    className="w-full"
                                    loading={
                                        mutation.busy && action === "apply"
                                    }
                                    disabled={mutation.busy || uploads.busy}
                                >
                                    {t("Save and continue")}
                                </Button>
                                {dirty && (
                                    <p
                                        role="status"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {t("Unsaved changes")}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        "New posts are submitted for review before publication.",
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </fieldset>
            </form>
        </MemberLayout>
    );
}
