import { useRef, useState, type FormEvent } from "react";
import { router } from "@inertiajs/react";
import { AvatarCropper } from "@admin/../../../../acl/resources/js/admin/AvatarCropper";
import { Field } from "@admin/components/field";
import { SafeRichText } from "@admin/components/safe-rich-text";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    MemberLayout,
    MemberErrors,
    useMember,
    useMemberDraft,
    useMemberMutation,
} from "./shared";
type Profile = {
    first_name: string;
    last_name: string;
    phone: string;
    dob: string;
    description: string;
    gender: string;
};
export default function Settings({
    profile,
    extensionTabs,
    extensionContent,
}: {
    profile: Profile;
    extensionTabs?: string | null;
    extensionContent?: string | null;
}) {
    const { member, urls } = useMember();
    const t = useAdminTranslation();
    const [values, setValues] = useState<Profile>(profile);
    const [baseline, setBaseline] = useState(JSON.stringify(profile));
    const [passwords, setPasswords] = useState({
        old_password: "",
        password: "",
        password_confirmation: "",
    });
    const [file, setFile] = useState<File | null>(null);
    const [avatar, setAvatar] = useState(member.avatar);
    const fileInput = useRef<HTMLInputElement>(null);
    const profileMutation = useMemberMutation();
    const passwordMutation = useMemberMutation();
    const avatarMutation = useMemberMutation();
    const dirty =
        JSON.stringify(values) !== baseline ||
        Object.values(passwords).some(Boolean) ||
        !!file;
    useMemberDraft(dirty);
    const update = (key: keyof Profile, value: string) =>
        setValues((current) => ({ ...current, [key]: value }));
    function saveProfile(event: FormEvent) {
        event.preventDefault();
        const submitted = { ...values };
        void profileMutation.run(urls.profile, submitted, "POST", () =>
            setBaseline(JSON.stringify(submitted)),
        );
    }
    function savePassword(event: FormEvent) {
        event.preventDefault();
        void passwordMutation.run(urls.security, passwords, "PUT", () =>
            setPasswords({
                old_password: "",
                password: "",
                password_confirmation: "",
            }),
        );
    }
    function saveAvatar(cropped: File) {
        setFile(null);
        const body = new FormData();
        body.append("avatar_file", cropped);
        void avatarMutation.run(urls.avatar, body, "POST", (result) => {
            if (result.data?.url) setAvatar(result.data.url);
            router.reload({ only: ["member"] });
        });
    }
    return (
        <MemberLayout title={t("Account settings")}>
            {extensionTabs && <SafeRichText html={extensionTabs} />}
            <div className="grid items-start gap-5 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{t("Profile")}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={saveProfile} className="space-y-5">
                            <fieldset
                                disabled={profileMutation.busy}
                                className="space-y-5"
                            >
                                {(
                                    [
                                        ["first_name", "First name"],
                                        ["last_name", "Last name"],
                                        ["phone", "Phone"],
                                        ["dob", "Date of birth"],
                                    ] as const
                                ).map(([key, label]) => (
                                    <Field
                                        key={key}
                                        label={t(label)}
                                        required={[
                                            "first_name",
                                            "last_name",
                                        ].includes(key)}
                                        error={profileMutation.errors[key]}
                                    >
                                        {(id) => (
                                            <Input
                                                id={id}
                                                type={
                                                    key === "dob"
                                                        ? "date"
                                                        : key === "phone"
                                                          ? "tel"
                                                          : "text"
                                                }
                                                name={key}
                                                required={[
                                                    "first_name",
                                                    "last_name",
                                                ].includes(key)}
                                                maxLength={
                                                    key === "phone" ? 30 : 120
                                                }
                                                value={values[key] ?? ""}
                                                autoComplete={
                                                    key === "first_name"
                                                        ? "given-name"
                                                        : key === "last_name"
                                                          ? "family-name"
                                                          : key === "phone"
                                                            ? "tel"
                                                            : "bday"
                                                }
                                                onChange={(event) =>
                                                    update(
                                                        key,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </Field>
                                ))}
                                <Field label={t("Email")}>
                                    {(id) => (
                                        <Input
                                            id={id}
                                            type="email"
                                            value={member.email}
                                            readOnly
                                            autoComplete="email"
                                        />
                                    )}
                                </Field>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        member.verified
                                            ? "Email verified"
                                            : "Email not verified",
                                    )}
                                </p>
                                {!member.verified && (
                                    <a
                                        href={urls.resendConfirmation}
                                        className="text-sm text-primary-ink underline"
                                    >
                                        {t("Resend verification email")}
                                    </a>
                                )}
                                <Field
                                    label={t("Gender")}
                                    error={profileMutation.errors.gender}
                                >
                                    {(id) => (
                                        <Select
                                            id={id}
                                            value={values.gender ?? ""}
                                            onValueChange={(value) =>
                                                update("gender", value)
                                            }
                                        >
                                            <option value="">
                                                {t("Select")}
                                            </option>
                                            <option value="male">
                                                {t("Male")}
                                            </option>
                                            <option value="female">
                                                {t("Female")}
                                            </option>
                                            <option value="other">
                                                {t("Other")}
                                            </option>
                                        </Select>
                                    )}
                                </Field>
                                <Field
                                    label={t("Description")}
                                    error={profileMutation.errors.description}
                                >
                                    {(id) => (
                                        <Textarea
                                            id={id}
                                            maxLength={2000}
                                            value={values.description ?? ""}
                                            onChange={(event) =>
                                                update(
                                                    "description",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                                <MemberErrors errors={profileMutation.errors} />
                                <Button
                                    type="submit"
                                    loading={profileMutation.busy}
                                >
                                    {t("Save changes")}
                                </Button>
                            </fieldset>
                        </form>
                    </CardContent>
                </Card>
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Profile picture")}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <img
                                src={avatar}
                                alt={t("Current profile picture")}
                                className="size-28 rounded-full object-cover"
                            />
                            <Field
                                label={t("Choose an image")}
                                error={avatarMutation.errors.avatar_file}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        ref={fileInput}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/gif,image/bmp"
                                        disabled={avatarMutation.busy}
                                        onChange={(event) => {
                                            const selected =
                                                event.target.files?.[0];
                                            if (selected) setFile(selected);
                                            event.target.value = "";
                                        }}
                                    />
                                )}
                            </Field>
                            {avatarMutation.busy && (
                                <p role="status">{t("Uploading…")}</p>
                            )}
                            <MemberErrors errors={avatarMutation.errors} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Change password")}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={savePassword} className="space-y-5">
                                <fieldset
                                    disabled={passwordMutation.busy}
                                    className="space-y-5"
                                >
                                    {(
                                        [
                                            [
                                                "old_password",
                                                "Current password",
                                            ],
                                            ["password", "New password"],
                                            [
                                                "password_confirmation",
                                                "Confirm password",
                                            ],
                                        ] as const
                                    ).map(([key, label]) => (
                                        <Field
                                            key={key}
                                            label={t(label)}
                                            error={passwordMutation.errors[key]}
                                            required
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    type="password"
                                                    name={key}
                                                    required
                                                    minLength={6}
                                                    maxLength={60}
                                                    autoComplete={
                                                        key === "old_password"
                                                            ? "current-password"
                                                            : "new-password"
                                                    }
                                                    value={passwords[key]}
                                                    onChange={(event) =>
                                                        setPasswords(
                                                            (current) => ({
                                                                ...current,
                                                                [key]: event
                                                                    .target
                                                                    .value,
                                                            }),
                                                        )
                                                    }
                                                />
                                            )}
                                        </Field>
                                    ))}
                                    <MemberErrors
                                        errors={passwordMutation.errors}
                                    />
                                    <Button
                                        type="submit"
                                        loading={passwordMutation.busy}
                                    >
                                        {t("Update password")}
                                    </Button>
                                </fieldset>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
            {extensionContent && <SafeRichText html={extensionContent} />}
            {file && (
                <AvatarCropper
                    file={file}
                    onClose={() => setFile(null)}
                    onApply={saveAvatar}
                />
            )}
        </MemberLayout>
    );
}
