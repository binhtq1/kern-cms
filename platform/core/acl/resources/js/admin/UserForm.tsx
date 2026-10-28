import { draftSnapshot } from "@admin/lib/draft-state";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { AvatarCropper } from "./AvatarCropper";
import { setTheme } from "@admin/lib/theme";
import { useState, useRef } from "react";
import { Dialog } from "@admin/components/ui/dialog";
import { Link, router, useForm } from "@inertiajs/react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Field } from "@admin/components/field";
import { useAdminTranslation } from "@admin/lib/translate";

type Option = { value: string; label: string };
type Props = {
    user: {
        id: number;
        first_name: string;
        last_name: string;
        username: string;
        email: string;
        role_id: string;
        avatar_url: string;
    } | null;
    roles: Option[];
    locales: Option[];
    isSelf: boolean;
    requiresCurrentPassword: boolean;
    canAssignRole: boolean;
    canAvatar: boolean;
    superActions: { url: string; revoke: boolean } | null;
    preferences: {
        locale: string;
        locale_direction: string;
        theme_mode: string;
    };
    urls: {
        index: string;
        submit: string;
        password: string | null;
        preferences: string | null;
        avatar: string | null;
        removeAvatar: string | null;
        assign: string;
    };
};
export default function UserForm({
    user,
    roles,
    locales,
    isSelf,
    requiresCurrentPassword,
    canAssignRole,
    canAvatar,
    superActions,
    preferences,
    urls,
}: Props) {
    const t = useAdminTranslation();
    const [confirmSuper, setConfirmSuper] = useState(false);
    const [superBusy, setSuperBusy] = useState(false);
    const form = useForm({
        first_name: user?.first_name ?? "",
        last_name: user?.last_name ?? "",
        username: user?.username ?? "",
        email: user?.email ?? "",
        password: "",
        password_confirmation: "",
        role_id: "",
        submitter: "apply",
    });
    const password = useForm({
        old_password: "",
        password: "",
        password_confirmation: "",
    });
    const preference = useForm({ ...preferences });
    const assignment = useForm({
        pk: user?.id ?? 0,
        value: user?.role_id ?? "",
    });
    const [cropFile, setCropFile] = useState<File | null>(null);
    const avatar = useForm<{ avatar_file: File | null }>({ avatar_file: null });
    useUnsavedChanges(
        form.isDirty ||
            password.isDirty ||
            preference.isDirty ||
            assignment.isDirty ||
            !!cropFile ||
            avatar.isDirty,
        form.processing ||
            password.processing ||
            preference.processing ||
            assignment.processing ||
            avatar.processing,
    );
    const passwordDraft = useRef(password.data);
    passwordDraft.current = password.data;
    const avatarDraft = useRef(avatar.data.avatar_file);
    avatarDraft.current = avatar.data.avatar_file;
    const labels = {
        first_name: "First name",
        last_name: "Last name",
        username: "Username",
        email: "Email",
        password: "Password",
        password_confirmation: "Confirm password",
    };
    return (
        <AdminLayout
            title={t(user ? "Edit user" : "Create user")}
            actions={
                <Button asChild variant="outline">
                    <Link href={urls.index}>{t("Back")}</Link>
                </Button>
            }
        >
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Profile")}</CardTitle>
                        </CardHeader>
                        <CardContent className="p-5">
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    user
                                        ? form.put(urls.submit, {
                                              onSuccess: () =>
                                                  form.setDefaults(form.data),
                                          })
                                        : form.post(urls.submit, {
                                              onSuccess: () =>
                                                  form.setDefaults(form.data),
                                          });
                                }}
                                className="space-y-4"
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {(
                                        [
                                            "first_name",
                                            "last_name",
                                            "username",
                                            "email",
                                        ] as const
                                    ).map((key) => (
                                        <Field
                                            key={key}
                                            label={t(labels[key])}
                                            error={form.errors[key]}
                                            required
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    type={
                                                        key === "email"
                                                            ? "email"
                                                            : "text"
                                                    }
                                                    required
                                                    autoComplete={
                                                        key === "username"
                                                            ? "username"
                                                            : key === "email"
                                                              ? "email"
                                                              : key ===
                                                                  "first_name"
                                                                ? "given-name"
                                                                : "family-name"
                                                    }
                                                    value={form.data[key]}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            key,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </Field>
                                    ))}
                                </div>
                                {!user && (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            {(
                                                [
                                                    "password",
                                                    "password_confirmation",
                                                ] as const
                                            ).map((key) => (
                                                <Field
                                                    key={key}
                                                    label={t(labels[key])}
                                                    required
                                                    error={form.errors[key]}
                                                >
                                                    {(id) => (
                                                        <Input
                                                            id={id}
                                                            required
                                                            type="password"
                                                            autoComplete="new-password"
                                                            value={
                                                                form.data[key]
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    key,
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </Field>
                                            ))}
                                        </div>
                                        <Field
                                            label={t("Role")}
                                            error={form.errors.role_id}
                                        >
                                            {(id) => (
                                                <Select
                                                    id={id}
                                                    value={form.data.role_id}
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            "role_id",
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        {t("No role")}
                                                    </option>
                                                    {roles.map((role) => (
                                                        <option
                                                            key={role.value}
                                                            value={role.value}
                                                        >
                                                            {role.label}
                                                        </option>
                                                    ))}
                                                </Select>
                                            )}
                                        </Field>
                                    </>
                                )}
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        loading={form.processing}
                                    >
                                        {t("Save")}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                    {user && urls.password && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Change password")}</CardTitle>
                            </CardHeader>
                            <CardContent className="p-5">
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        password.put(urls.password!, {
                                            onSuccess: () => {
                                                password.setDefaults({
                                                    old_password: "",
                                                    password: "",
                                                    password_confirmation: "",
                                                });
                                                if (
                                                    draftSnapshot(
                                                        passwordDraft.current,
                                                    ) ===
                                                    draftSnapshot(password.data)
                                                )
                                                    password.reset();
                                            },
                                        });
                                    }}
                                >
                                    {requiresCurrentPassword && (
                                        <Field
                                            label={t("Current password")}
                                            error={password.errors.old_password}
                                            required
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    type="password"
                                                    autoComplete="current-password"
                                                    value={
                                                        password.data
                                                            .old_password
                                                    }
                                                    onChange={(event) =>
                                                        password.setData(
                                                            "old_password",
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                            )}
                                        </Field>
                                    )}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {(
                                            [
                                                "password",
                                                "password_confirmation",
                                            ] as const
                                        ).map((key) => (
                                            <Field
                                                key={key}
                                                label={t(
                                                    key === "password"
                                                        ? "New password"
                                                        : "Confirm password",
                                                )}
                                                error={password.errors[key]}
                                                required
                                            >
                                                {(id) => (
                                                    <Input
                                                        id={id}
                                                        required
                                                        type="password"
                                                        autoComplete="new-password"
                                                        value={
                                                            password.data[key]
                                                        }
                                                        onChange={(event) =>
                                                            password.setData(
                                                                key,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </Field>
                                        ))}
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            loading={password.processing}
                                        >
                                            {t("Update password")}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                </div>
                <div className="space-y-5">
                    {user && canAvatar && urls.avatar && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Avatar")}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 p-5">
                                <img
                                    src={user.avatar_url}
                                    alt=""
                                    className="size-20 rounded-lg object-cover"
                                />
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        avatar.post(urls.avatar!, {
                                            forceFormData: true,
                                            onSuccess: () => {
                                                avatar.setDefaults({
                                                    avatar_file: null,
                                                });
                                                if (
                                                    avatarDraft.current ===
                                                    avatar.data.avatar_file
                                                )
                                                    avatar.reset();
                                            },
                                        });
                                    }}
                                >
                                    <Field
                                        label={t("Upload image")}
                                        error={avatar.errors.avatar_file}
                                    >
                                        {(id) => (
                                            <Input
                                                id={id}
                                                type="file"
                                                accept="image/*"
                                                onChange={(event) => {
                                                    const file =
                                                        event.target.files?.[0];
                                                    if (file) setCropFile(file);
                                                    event.target.value = "";
                                                }}
                                            />
                                        )}
                                    </Field>
                                    {avatar.data.avatar_file && (
                                        <p className="text-xs text-muted-foreground">
                                            {avatar.data.avatar_file.name}
                                        </p>
                                    )}
                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={!avatar.data.avatar_file}
                                            loading={avatar.processing}
                                        >
                                            {t("Upload")}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                urls.removeAvatar &&
                                                router.post(urls.removeAvatar)
                                            }
                                        >
                                            {t("Remove")}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                    {user && canAssignRole && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Role")}</CardTitle>
                            </CardHeader>
                            <CardContent className="p-5">
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        assignment.post(urls.assign, {
                                            onSuccess: () =>
                                                assignment.setDefaults(
                                                    assignment.data,
                                                ),
                                        });
                                    }}
                                >
                                    <Field
                                        label={t("Role")}
                                        error={assignment.errors.value}
                                    >
                                        {(id) => (
                                            <Select
                                                id={id}
                                                required
                                                value={assignment.data.value}
                                                onValueChange={(value) =>
                                                    assignment.setData(
                                                        "value",
                                                        value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    {t("Select role")}
                                                </option>
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </Select>
                                        )}
                                    </Field>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        loading={assignment.processing}
                                    >
                                        {t("Assign role")}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                    {user && urls.preferences && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Preferences")}</CardTitle>
                            </CardHeader>
                            <CardContent className="p-5">
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        preference.put(urls.preferences!, {
                                            onSuccess: () => {
                                                preference.setDefaults(
                                                    preference.data,
                                                );
                                                const mode =
                                                    preference.data.theme_mode;
                                                if (
                                                    isSelf &&
                                                    (mode === "light" ||
                                                        mode === "dark")
                                                )
                                                    setTheme(mode);
                                            },
                                        });
                                    }}
                                >
                                    <Field
                                        label={t("Language")}
                                        error={preference.errors.locale}
                                    >
                                        {(id) => (
                                            <Select
                                                id={id}
                                                value={preference.data.locale}
                                                onValueChange={(value) =>
                                                    preference.setData(
                                                        "locale",
                                                        value,
                                                    )
                                                }
                                            >
                                                {locales.map((locale) => (
                                                    <option
                                                        key={locale.value}
                                                        value={locale.value}
                                                    >
                                                        {locale.label}
                                                    </option>
                                                ))}
                                            </Select>
                                        )}
                                    </Field>
                                    <Field
                                        label={t("Text direction")}
                                        error={
                                            preference.errors.locale_direction
                                        }
                                    >
                                        {(id) => (
                                            <Select
                                                id={id}
                                                value={
                                                    preference.data
                                                        .locale_direction
                                                }
                                                onValueChange={(value) =>
                                                    preference.setData(
                                                        "locale_direction",
                                                        value,
                                                    )
                                                }
                                            >
                                                <option value="ltr">
                                                    {t("Left to right")}
                                                </option>
                                                <option value="rtl">
                                                    {t("Right to left")}
                                                </option>
                                            </Select>
                                        )}
                                    </Field>
                                    <Field
                                        label={t("Theme")}
                                        error={preference.errors.theme_mode}
                                    >
                                        {(id) => (
                                            <Select
                                                id={id}
                                                value={
                                                    preference.data.theme_mode
                                                }
                                                onValueChange={(value) =>
                                                    preference.setData(
                                                        "theme_mode",
                                                        value,
                                                    )
                                                }
                                            >
                                                <option value="light">
                                                    {t("Light")}
                                                </option>
                                                <option value="dark">
                                                    {t("Dark")}
                                                </option>
                                            </Select>
                                        )}
                                    </Field>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        loading={preference.processing}
                                    >
                                        {t("Save preferences")}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                    {superActions && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Super user access")}</CardTitle>
                            </CardHeader>
                            <CardContent className="p-5">
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirmSuper(true)}
                                >
                                    {t(
                                        superActions.revoke
                                            ? "Revoke super user"
                                            : "Grant super user",
                                    )}
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
            <Dialog
                open={confirmSuper}
                onOpenChange={(open) => !superBusy && setConfirmSuper(open)}
                title={t(
                    superActions?.revoke
                        ? "Revoke super user"
                        : "Grant super user",
                )}
                description={t(
                    "Super users have full access to all administration features.",
                )}
            >
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={superBusy}
                        onClick={() => setConfirmSuper(false)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        loading={superBusy}
                        onClick={() => {
                            if (!superActions) return;
                            setSuperBusy(true);
                            router.visit(superActions.url, {
                                preserveState: true,
                                method: superActions.revoke ? "delete" : "post",
                                onSuccess: () => setConfirmSuper(false),
                                onFinish: () => setSuperBusy(false),
                            });
                        }}
                    >
                        {t("Confirm")}
                    </Button>
                </div>
            </Dialog>
            {cropFile && (
                <AvatarCropper
                    file={cropFile}
                    onClose={() => setCropFile(null)}
                    onApply={(file) => {
                        avatar.setData("avatar_file", file);
                        setCropFile(null);
                    }}
                />
            )}
        </AdminLayout>
    );
}
