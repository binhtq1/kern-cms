import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import {
    setupStepPayload,
    setupHasPendingChanges,
    setupExitCookie,
} from "./setup-state";
import {
    SetupBrandingFields,
    SetupAccountFields,
    type SetupData,
} from "./setup-fields";
import { Head } from "@inertiajs/react";
import { useState, useRef, useEffect } from "react";
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CircleCheck,
    Globe,
    Palette,
    ShieldCheck,
    SlidersHorizontal,
} from "lucide-react";
import { Dialog } from "@admin/components/ui/dialog";
import axios from "axios";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent, CardFooter } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import { isRecord } from "@admin/lib/admin-api";

const steps = [
    { number: 1, label: "Welcome", description: "A quick overview" },
    { number: 2, label: "Branding", description: "Your website and admin" },
    { number: 3, label: "Account", description: "Administrator details" },
    { number: 4, label: "Ready", description: "Review and finish" },
] as const;

export default function Setup({
    submit,
    dashboard,
    site,
    user,
    media,
    themeFields,
    fonts,
}: {
    submit: string;
    dashboard: string;
    site: Pick<
        SetupData,
        | "site_title"
        | "logo"
        | "favicon"
        | "primary_color"
        | "primary_font"
        | "admin_logo"
        | "admin_favicon"
    >;
    themeFields: { primary_color: boolean; primary_font: boolean };
    fonts: { value: string; label: string }[];
    user: { username: string; email: string };
    media: { list: string | null; upload: string | null };
}) {
    const t = useAdminTranslation();
    const [step, setStep] = useState(1);
    const [previousSteps, setPreviousSteps] = useState<number[]>([]);
    const [accountSkipped, setAccountSkipped] = useState(false);
    const [exitOpen, setExitOpen] = useState(false);
    const [exiting, setExiting] = useState(false);
    const backToWizard = useRef<HTMLButtonElement>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const previousStep = useRef(step);
    const focusError = useRef(false);
    const requestPending = useRef(false);
    const [data, setData] = useState<SetupData>({
        ...site,
        ...user,
        password: "",
        password_confirmation: "",
    });
    const [savedSite, setSavedSite] = useState(() =>
        JSON.stringify(setupStepPayload(data, 2, themeFields)),
    );
    const [savedAccount, setSavedAccount] = useState(() =>
        JSON.stringify(setupStepPayload(data, 3, themeFields)),
    );
    const latestData = useRef(data);
    latestData.current = data;
    const siteDirty =
        JSON.stringify(setupStepPayload(data, 2, themeFields)) !== savedSite;
    const accountDirty =
        JSON.stringify(setupStepPayload(data, 3, themeFields)) !== savedAccount;
    useUnsavedChanges(!exiting && (siteDirty || accountDirty));
    useEffect(() => {
        if (!exiting) return;
        document.cookie = setupExitCookie(window.location.hostname);
        window.location.assign(dashboard);
    }, [exiting, dashboard]);
    useEffect(() => {
        if (previousStep.current !== step) headingRef.current?.focus();
        previousStep.current = step;
    }, [step]);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState("");
    const [messageIsError, setMessageIsError] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    useEffect(() => {
        if (!focusError.current) return;
        focusError.current = false;
        formRef.current
            ?.querySelector<HTMLElement>(
                "input[aria-invalid=true], button[aria-invalid=true]",
            )
            ?.focus();
    }, [errors]);
    function change(name: keyof SetupData, value: string) {
        setData((current) => ({ ...current, [name]: value }));
        setErrors((current) => {
            if (!current[name]) return current;
            const next = { ...current };
            delete next[name];
            return next;
        });
    }
    async function next() {
        if (requestPending.current || exiting) return;
        requestPending.current = true;
        setBusy(true);
        setMessage("");
        setErrors({});
        try {
            const payload = setupStepPayload(data, step, themeFields);
            const response = await axios.post(submit, { step, ...payload });
            if (response.data.error) throw new Error(response.data.message);
            let nextStep = response.data?.data?.step;
            if (
                !Number.isInteger(nextStep) ||
                nextStep <= step ||
                nextStep > 5
            ) {
                throw new Error(t("Something went wrong"));
            }
            if (step === 2) setSavedSite(JSON.stringify(payload));
            if (step === 3) setSavedAccount(JSON.stringify(payload));
            if (
                setupHasPendingChanges(
                    payload,
                    latestData.current,
                    step,
                    themeFields,
                )
            ) {
                setMessageIsError(false);
                setMessage(t("Saved. You have newer unsaved changes."));
            } else if (nextStep > 4) {
                window.location.assign(dashboard);
            } else {
                if (step === 2) {
                    // Keep an account draft reachable even when the server can skip this step.
                    if (nextStep === 4 && accountDirty) nextStep = 3;
                    setAccountSkipped(nextStep === 4);
                }
                setPreviousSteps((current) => [...current, step]);
                setStep(nextStep);
            }
        } catch (error) {
            if (
                axios.isAxiosError(error) &&
                isRecord(error.response?.data?.errors)
            ) {
                const validationErrors = Object.fromEntries(
                    Object.entries(error.response.data.errors)
                        .map(([name, messages]) => [
                            name,
                            Array.isArray(messages) ? messages[0] : messages,
                        ])
                        .filter(
                            (entry): entry is [string, string] =>
                                typeof entry[1] === "string",
                        ),
                );
                focusError.current = true;
                setErrors(validationErrors);
            }
            setMessageIsError(true);
            setMessage(
                axios.isAxiosError(error)
                    ? (error.response?.data?.message ?? error.message)
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
            );
        } finally {
            requestPending.current = false;
            setBusy(false);
        }
    }
    const title = [
        "Set up your website",
        "Make it your own",
        "Review your administrator account",
        "Your website is ready",
    ][step - 1];
    const description = [
        "Configure your site and update the administrator account.",
        "Add your identity and choose the essentials for your website.",
        "Confirm your sign-in details and choose a password for this account.",
        "Review your details, then open the dashboard to start working.",
    ][step - 1];

    return (
        <AdminLayout title={t("Get started")}>
            <Head title={t("Get started")} />
            <div className="mx-auto grid max-w-5xl items-start gap-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-8">
                <aside className="space-y-5 lg:sticky lg:top-24">
                    <div className="hidden space-y-2 lg:block">
                        <p className="text-xs font-semibold uppercase tracking-wider text-primary-ink">
                            {t("Quick setup")}
                        </p>
                        <p className="text-sm leading-6 text-muted-foreground">
                            {t("A few essentials to make this website yours.")}
                        </p>
                    </div>
                    <nav aria-label={t("Setup progress")}>
                        <ol className="grid grid-cols-4 gap-2 lg:grid-cols-1 lg:gap-5">
                            {steps.map((item) => {
                                const current = step === item.number;
                                const completed = previousSteps.includes(
                                    item.number,
                                );
                                const skipped =
                                    item.number === 3 && accountSkipped;
                                return (
                                    <li
                                        key={item.number}
                                        aria-current={
                                            current ? "step" : undefined
                                        }
                                        className="flex min-w-0 flex-col items-center gap-2 lg:flex-row lg:items-start lg:gap-3"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                "flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold",
                                                current
                                                    ? "border-primary bg-primary text-primary-foreground"
                                                    : completed
                                                      ? "border-primary/20 bg-primary/10 text-primary-ink"
                                                      : "border-border bg-card text-muted-foreground",
                                            )}
                                        >
                                            {completed ? (
                                                <Check className="size-4" />
                                            ) : skipped ? (
                                                "–"
                                            ) : (
                                                item.number
                                            )}
                                        </span>
                                        <div className="min-w-0 text-center lg:text-start">
                                            <p
                                                className={cn(
                                                    "text-xs font-medium sm:text-sm",
                                                    !current &&
                                                        !completed &&
                                                        "text-muted-foreground",
                                                )}
                                            >
                                                {t(item.label)}
                                            </p>
                                            <p className="mt-1 hidden text-xs leading-5 text-muted-foreground lg:block">
                                                {t(
                                                    skipped
                                                        ? "Not required"
                                                        : item.description,
                                                )}
                                            </p>
                                            {completed && (
                                                <span className="sr-only">
                                                    {t("Completed")}
                                                </span>
                                            )}
                                            {skipped && (
                                                <span className="sr-only lg:hidden">
                                                    {t("Not required")}
                                                </span>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>
                </aside>
                <form
                    ref={formRef}
                    aria-busy={busy}
                    onSubmit={(event) => {
                        event.preventDefault();
                        void next();
                    }}
                >
                    <Card>
                        <CardContent className="space-y-7 p-5 sm:p-7">
                            <header className="space-y-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {t("Step :current of :total", {
                                        current: step,
                                        total: 4,
                                    })}
                                </p>
                                <h2
                                    ref={headingRef}
                                    tabIndex={-1}
                                    className="scroll-mt-24 text-xl font-semibold tracking-tight outline-none sm:text-2xl"
                                >
                                    {t(title)}
                                </h2>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {t(description)}
                                </p>
                            </header>
                            {step === 1 && (
                                <div className="space-y-5 border-t pt-6">
                                    {[
                                        {
                                            icon: Palette,
                                            title: "Website branding",
                                            description:
                                                "Choose your site name, logos, colors and font.",
                                        },
                                        {
                                            icon: ShieldCheck,
                                            title: "Administrator account",
                                            description:
                                                "Review the account used to manage your website.",
                                        },
                                        {
                                            icon: SlidersHorizontal,
                                            title: "Ready to work",
                                            description:
                                                "Finish setup and continue to your dashboard.",
                                        },
                                    ].map(
                                        ({
                                            icon: Icon,
                                            title,
                                            description,
                                        }) => (
                                            <div
                                                key={title}
                                                className="flex gap-4"
                                            >
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary-ink">
                                                    <Icon
                                                        aria-hidden="true"
                                                        className="size-5"
                                                    />
                                                </span>
                                                <div className="space-y-1">
                                                    <h3 className="text-sm font-medium">
                                                        {t(title)}
                                                    </h3>
                                                    <p className="text-sm leading-6 text-muted-foreground">
                                                        {t(description)}
                                                    </p>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                    <p className="rounded-md bg-muted/50 px-4 py-3 text-xs leading-5 text-muted-foreground">
                                        {t(
                                            "You can change these settings later.",
                                        )}
                                    </p>
                                </div>
                            )}
                            {step === 2 && (
                                <SetupBrandingFields
                                    data={data}
                                    errors={errors}
                                    disabled={busy}
                                    onChange={change}
                                    media={media}
                                    themeFields={themeFields}
                                    fonts={fonts}
                                />
                            )}
                            {step === 3 && (
                                <SetupAccountFields
                                    data={data}
                                    errors={errors}
                                    disabled={busy}
                                    onChange={change}
                                />
                            )}
                            {step === 4 && (
                                <div className="space-y-6 border-t pt-6">
                                    <div className="flex items-center gap-3 rounded-md bg-primary/5 p-4">
                                        <CircleCheck
                                            aria-hidden="true"
                                            className="size-6 shrink-0 text-primary-ink"
                                        />
                                        <p className="text-sm font-medium">
                                            {t(
                                                "Your settings are saved. Open the dashboard to finish setup.",
                                            )}
                                        </p>
                                    </div>
                                    <dl className="divide-y text-sm">
                                        {[
                                            {
                                                icon: Globe,
                                                label: "Site title",
                                                value: data.site_title || "—",
                                            },
                                            {
                                                icon: ShieldCheck,
                                                label: "Username",
                                                value: data.username,
                                            },
                                            {
                                                icon: null,
                                                label: "Email",
                                                value: data.email,
                                            },
                                        ].map(
                                            ({ icon: Icon, label, value }) => (
                                                <div
                                                    key={label}
                                                    className="flex flex-wrap justify-between gap-x-5 gap-y-1 py-3"
                                                >
                                                    <dt className="flex items-center gap-2 text-muted-foreground">
                                                        {Icon && (
                                                            <Icon
                                                                aria-hidden="true"
                                                                className="size-4"
                                                            />
                                                        )}
                                                        {t(label)}
                                                    </dt>
                                                    <dd className="min-w-0 break-all font-medium">
                                                        {value}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                </div>
                            )}
                            {message && (
                                <p
                                    role={messageIsError ? "alert" : "status"}
                                    className={cn(
                                        "rounded-md border p-3 text-sm leading-6",
                                        messageIsError
                                            ? "border-destructive/25 bg-destructive/5 text-destructive"
                                            : "bg-muted text-muted-foreground",
                                    )}
                                >
                                    {message}
                                </p>
                            )}
                        </CardContent>
                        <CardFooter className="sticky bottom-0 z-10 flex-wrap justify-between gap-3 rounded-b-lg bg-card px-5 py-4 sm:px-7">
                            <Button
                                variant="ghost"
                                disabled={busy || exiting}
                                onClick={() => setExitOpen(true)}
                                className="text-muted-foreground"
                            >
                                {t("Exit wizard")}
                            </Button>
                            <div className="flex flex-wrap items-center gap-2">
                                {previousSteps.length > 0 && (
                                    <Button
                                        variant="outline"
                                        disabled={busy || exiting}
                                        onClick={() => {
                                            setStep(
                                                previousSteps[
                                                    previousSteps.length - 1
                                                ],
                                            );
                                            setPreviousSteps((current) =>
                                                current.slice(0, -1),
                                            );
                                            setMessage("");
                                            setErrors({});
                                        }}
                                    >
                                        <ArrowLeft
                                            aria-hidden="true"
                                            className="rtl:rotate-180"
                                        />
                                        {t("Back")}
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    loading={busy}
                                    disabled={exiting}
                                >
                                    {t(
                                        step === 1
                                            ? "Get started"
                                            : step === 4
                                              ? "Open dashboard"
                                              : "Save and continue",
                                    )}
                                    {!busy && (
                                        <ArrowRight
                                            aria-hidden="true"
                                            className="rtl:rotate-180"
                                        />
                                    )}
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>
                </form>
            </div>
            <Dialog
                open={exitOpen}
                onOpenChange={setExitOpen}
                title={t("Exit wizard?")}
                description={t(
                    "Are you sure you want to exit the Quick Setup Wizard?",
                )}
                size="sm"
                initialFocusRef={backToWizard}
                footer={
                    <>
                        <Button
                            ref={backToWizard}
                            variant="outline"
                            onClick={() => setExitOpen(false)}
                        >
                            {t("Back to wizard")}
                        </Button>
                        <Button
                            onClick={() => {
                                setExitOpen(false);
                                setExiting(true);
                            }}
                        >
                            {t("Yes, I'll finish it later")}
                        </Button>
                    </>
                }
            >
                <p className="text-sm leading-6 text-muted-foreground">
                    {t(
                        "Saved steps are kept. Any unsaved changes will be lost.",
                    )}
                </p>
            </Dialog>
        </AdminLayout>
    );
}
