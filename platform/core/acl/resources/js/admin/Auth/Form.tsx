import { AppearanceBridge } from "@admin/components/admin-appearance";
import type { SharedProps } from "@admin/types";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { PasswordInput } from "@admin/components/ui/password-input";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Field } from "@admin/components/field";
import { ToastHost } from "@admin/components/toast";
import { resolveLoginBackground } from "../lib/login-background";
import { cn } from "@admin/lib/utils";
import { useAdminTranslation } from "@admin/lib/translate";
import { ArrowLeft, KeyRound, LogIn, Mail } from "lucide-react";

const loginBackgroundAssets = import.meta.glob(
    "/platform/core/acl/resources/js/admin/assets/backgrounds/*.jpg",
    { query: "?url", import: "default", eager: true },
);

type Props = {
    mode: "login" | "forgot" | "reset";
    title: string;
    description: string;
    logo: string | null;
    submit: string;
    links: { login: string; forgot: string };
    email: string;
    token: string;
    labels: Record<string, string>;
    credentials?: { username: string; password: string };
};
export default function AuthForm({
    mode,
    title,
    description,
    logo,
    submit,
    links,
    email,
    token,
    labels,
    credentials,
}: Props) {
    const t = useAdminTranslation();
    const { admin } = usePage<SharedProps>().props;
    const form = useForm({
        username: mode === "login" ? (credentials?.username ?? "") : "",
        email,
        password: mode === "login" ? (credentials?.password ?? "") : "",
        password_confirmation: "",
        remember: true,
        token,
    });
    const loginBackground = resolveLoginBackground(
        admin.appearance?.loginBackground,
        loginBackgroundAssets,
    );
    const SubmitIcon = { login: LogIn, forgot: Mail, reset: KeyRound }[mode];
    return (
        <main
            className={cn(
                "grid min-h-dvh bg-card text-foreground",
                loginBackground &&
                    "lg:grid-cols-2 xl:grid-cols-[minmax(24rem,1fr)_minmax(0,2fr)]",
            )}
        >
            <AppearanceBridge appearance={admin.appearance} />
            <Head title={title} />
            <section className="flex min-h-dvh min-w-0 flex-col justify-center border-t-4 border-primary px-6 py-10 sm:px-10 lg:px-8 xl:px-12">
                <div className="mx-auto w-full max-w-sm space-y-8">
                    <div className="space-y-6 text-center">
                        {admin.appearance?.logo || logo ? (
                            <img
                                src={admin.appearance?.logo || logo || ""}
                                alt={admin.name}
                                className="mx-auto max-w-full object-contain"
                                style={{
                                    maxHeight:
                                        admin.appearance?.logoHeight ?? 48,
                                }}
                            />
                        ) : (
                            <p className="text-lg font-semibold">
                                {admin.name}
                            </p>
                        )}
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    {description}
                                </p>
                            )}
                            {mode === "login" && !description && (
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    {t("Sign in to manage your website.")}
                                </p>
                            )}
                        </div>
                    </div>
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.transform((data) =>
                                mode === "login"
                                    ? {
                                          username: data.username,
                                          password: data.password,
                                          remember: data.remember,
                                      }
                                    : mode === "forgot"
                                      ? { email: data.email }
                                      : {
                                            email: data.email,
                                            password: data.password,
                                            password_confirmation:
                                                data.password_confirmation,
                                            token: data.token,
                                        },
                            );
                            form.post(submit, {
                                onFinish: () =>
                                    form.reset(
                                        "password",
                                        "password_confirmation",
                                    ),
                            });
                        }}
                        aria-busy={form.processing}
                    >
                        {mode === "login" ? (
                            <Field
                                label={labels.username}
                                required
                                error={
                                    form.errors.username || form.errors.email
                                }
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        name="username"
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        minLength={4}
                                        maxLength={30}
                                        placeholder={t(
                                            "Enter your username or email address",
                                        )}
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        value={form.data.username}
                                        onChange={(event) =>
                                            form.setData(
                                                "username",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        ) : (
                            <Field
                                label={labels.email}
                                required
                                error={form.errors.email}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        name="email"
                                        type="email"
                                        autoComplete="email"
                                        autoFocus
                                        required
                                        placeholder={t(
                                            "Enter your email address",
                                        )}
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                "email",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        )}
                        {mode !== "forgot" && (
                            <Field
                                label={labels.password}
                                required
                                error={form.errors.password}
                                description={
                                    mode === "reset"
                                        ? t("Use at least 6 characters.")
                                        : undefined
                                }
                            >
                                {(id) => (
                                    <PasswordInput
                                        id={id}
                                        name="password"
                                        placeholder={
                                            mode === "reset"
                                                ? labels.password_placeholder
                                                : undefined
                                        }
                                        autoComplete={
                                            mode === "login"
                                                ? "current-password"
                                                : "new-password"
                                        }
                                        required
                                        minLength={6}
                                        maxLength={
                                            mode === "login" ? 60 : undefined
                                        }
                                        value={form.data.password}
                                        onChange={(event) =>
                                            form.setData(
                                                "password",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        )}
                        {mode === "reset" && (
                            <Field
                                label={labels.confirmation}
                                required
                                error={form.errors.password_confirmation}
                            >
                                {(id) => (
                                    <PasswordInput
                                        id={id}
                                        name="password_confirmation"
                                        placeholder={
                                            labels.confirmation_placeholder
                                        }
                                        autoComplete="new-password"
                                        required
                                        minLength={6}
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData(
                                                "password_confirmation",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        )}
                        {form.errors.token && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.token}
                            </p>
                        )}
                        {mode === "login" && (
                            <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                                <label className="flex cursor-pointer items-center gap-1">
                                    <Checkbox
                                        name="remember"
                                        checked={form.data.remember}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                "remember",
                                                checked === true,
                                            )
                                        }
                                    />
                                    {labels.remember}
                                </label>
                                <Link
                                    href={links.forgot}
                                    className="inline-flex min-h-11 items-center rounded-sm py-2 text-primary-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    {labels.forgot}
                                </Link>
                            </div>
                        )}
                        <Button
                            type="submit"
                            className="w-full"
                            loading={form.processing}
                        >
                            {!form.processing && (
                                <SubmitIcon aria-hidden="true" />
                            )}
                            {labels.submit}
                        </Button>
                        {mode !== "login" && (
                            <p className="text-center text-sm">
                                <Link
                                    href={links.login}
                                    className="inline-flex min-h-11 items-center gap-2 rounded-sm text-primary-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <ArrowLeft
                                        aria-hidden="true"
                                        className="size-4 rtl:rotate-180"
                                    />
                                    {labels.back}
                                </Link>
                            </p>
                        )}
                    </form>
                </div>
            </section>
            {loginBackground && (
                <aside
                    className="relative hidden min-w-0 overflow-hidden bg-muted lg:block"
                    aria-label={admin.name}
                >
                    <div
                        className="absolute inset-0 bg-cover bg-center"
                        style={{
                            backgroundImage: `url(${JSON.stringify(loginBackground)})`,
                        }}
                    />
                    <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/10 to-transparent" />
                    <div className="absolute inset-x-0 bottom-0 space-y-2 p-10 text-end text-white xl:p-12">
                        <p className="text-3xl font-semibold tracking-tight">
                            {admin.name}
                        </p>
                        <p className="text-sm text-white/85">
                            {t("Administration")}
                        </p>
                    </div>
                </aside>
            )}
            <ToastHost />
        </main>
    );
}
