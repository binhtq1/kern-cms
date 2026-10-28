import { Field } from "@admin/components/field";
import { ColorPicker } from "@admin/components/color-picker";
import { FontPicker } from "@admin/components/font-picker";
import { MediaPicker } from "@admin/components/media-picker";
import { Input } from "@admin/components/ui/input";
import { PasswordInput } from "@admin/components/ui/password-input";
import { useAdminTranslation } from "@admin/lib/translate";

export type SetupData = {
    site_title: string;
    logo: string;
    favicon: string;
    admin_logo: string;
    admin_favicon: string;
    primary_color: string;
    primary_font: string;
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
};

type FieldProps = {
    data: SetupData;
    errors: Record<string, string>;
    disabled: boolean;
    onChange: (name: keyof SetupData, value: string) => void;
};

export function SetupBrandingFields({
    data,
    errors,
    disabled,
    onChange,
    media,
    themeFields,
    fonts,
}: FieldProps & {
    media: { list: string | null; upload: string | null };
    themeFields: { primary_color: boolean; primary_font: boolean };
    fonts: { value: string; label: string }[];
}) {
    const t = useAdminTranslation();
    const image = (
        name: "logo" | "favicon" | "admin_logo" | "admin_favicon",
        label: string,
    ) => (
        <Field label={t(label)} error={errors[name]}>
            {(id) => (
                <MediaPicker
                    id={id}
                    variant="featured"
                    value={data[name] ?? ""}
                    listUrl={media.list ?? undefined}
                    uploadUrl={media.upload ?? undefined}
                    disabled={disabled}
                    onChange={(value) => onChange(name, value)}
                />
            )}
        </Field>
    );

    return (
        <div className="space-y-7 divide-y [&>section+section]:pt-7">
            <section className="space-y-5">
                <div className="space-y-1">
                    <h3 className="text-sm font-semibold">
                        {t("Website identity")}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {t("Set the name and images visitors will see.")}
                    </p>
                </div>
                <Field label={t("Site title")} error={errors.site_title}>
                    {(id) => (
                        <Input
                            id={id}
                            name="site_title"
                            disabled={disabled}
                            value={data.site_title ?? ""}
                            onChange={(event) =>
                                onChange("site_title", event.target.value)
                            }
                        />
                    )}
                </Field>
                <div className="grid gap-5 sm:grid-cols-2">
                    {image("logo", "Logo")}
                    {image("favicon", "Favicon")}
                </div>
            </section>
            {(themeFields.primary_color || themeFields.primary_font) && (
                <section className="space-y-5">
                    <div className="space-y-1">
                        <h3 className="text-sm font-semibold">
                            {t("Colors and typography")}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {t("Choose the visual style of your website.")}
                        </p>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {themeFields.primary_color && (
                            <Field
                                label={t("Primary color")}
                                error={errors.primary_color}
                            >
                                {(id) => (
                                    <ColorPicker
                                        id={id}
                                        disabled={disabled}
                                        value={data.primary_color ?? ""}
                                        onChange={(value) =>
                                            onChange("primary_color", value)
                                        }
                                    />
                                )}
                            </Field>
                        )}
                        {themeFields.primary_font && (
                            <Field
                                label={t("Primary font")}
                                error={errors.primary_font}
                            >
                                {(id) => (
                                    <FontPicker
                                        id={id}
                                        disabled={disabled}
                                        value={data.primary_font ?? ""}
                                        choices={fonts}
                                        onChange={(value) =>
                                            onChange("primary_font", value)
                                        }
                                    />
                                )}
                            </Field>
                        )}
                    </div>
                </section>
            )}
            <section className="space-y-5">
                <div className="space-y-1">
                    <h3 className="text-sm font-semibold">
                        {t("Admin branding")}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            "Personalize the sign-in screen and admin browser tab.",
                        )}
                    </p>
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    {image("admin_logo", "Admin logo")}
                    {image("admin_favicon", "Admin favicon")}
                </div>
            </section>
        </div>
    );
}

export function SetupAccountFields({
    data,
    errors,
    disabled,
    onChange,
}: FieldProps) {
    const t = useAdminTranslation();

    return (
        <div className="space-y-6">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t("Username")} required error={errors.username}>
                    {(id) => (
                        <Input
                            id={id}
                            name="username"
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck={false}
                            minLength={4}
                            maxLength={30}
                            required
                            disabled={disabled}
                            value={data.username}
                            onChange={(event) =>
                                onChange("username", event.target.value)
                            }
                        />
                    )}
                </Field>
                <Field label={t("Email")} required error={errors.email}>
                    {(id) => (
                        <Input
                            id={id}
                            name="email"
                            type="email"
                            autoComplete="email"
                            autoCapitalize="none"
                            spellCheck={false}
                            minLength={6}
                            maxLength={60}
                            required
                            disabled={disabled}
                            value={data.email}
                            onChange={(event) =>
                                onChange("email", event.target.value)
                            }
                        />
                    )}
                </Field>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <Field
                    label={t("Password")}
                    required
                    error={errors.password}
                    description={t("Use at least 6 characters.")}
                >
                    {(id) => (
                        <PasswordInput
                            id={id}
                            name="password"
                            autoComplete="new-password"
                            minLength={6}
                            maxLength={60}
                            required
                            disabled={disabled}
                            value={data.password}
                            onChange={(event) =>
                                onChange("password", event.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    label={t("Confirm password")}
                    required
                    error={errors.password_confirmation}
                >
                    {(id) => (
                        <PasswordInput
                            id={id}
                            name="password_confirmation"
                            autoComplete="new-password"
                            minLength={6}
                            maxLength={60}
                            required
                            disabled={disabled}
                            value={data.password_confirmation}
                            onChange={(event) =>
                                onChange(
                                    "password_confirmation",
                                    event.target.value,
                                )
                            }
                        />
                    )}
                </Field>
            </div>
        </div>
    );
}
