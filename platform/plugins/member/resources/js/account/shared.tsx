import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState, type ReactNode } from "react";
import { Button } from "@admin/components/ui/button";
import { ToastHost, notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";

export type MemberProps = {
    member: {
        id: string;
        name: string;
        avatar: string;
        email: string;
        verified: boolean;
    };
    memberUrls: {
        home: string;
        dashboard: string;
        settings: string;
        profile: string;
        security: string;
        avatar: string;
        activity: string;
        upload: string;
        logout: string;
        resendConfirmation: string;
        posts: string | null;
        createPost: string | null;
        bulkPosts: string | null;
        bulkUpdatePosts: string | null;
    };
    [key: string]: unknown;
};
export function useMember() {
    const props = usePage<MemberProps>().props;
    return { ...props, urls: props.memberUrls };
}
export type MutationResult = {
    error?: boolean;
    message?: string;
    data?: { redirect_url?: string; url?: string; [key: string]: unknown };
};
export class MemberRequestError extends Error {
    constructor(
        message: string,
        public errors: Record<string, string> = {},
    ) {
        super(message);
    }
}
export async function memberRequest(
    url: string,
    data: FormData | Record<string, unknown> = {},
    method = "POST",
): Promise<MutationResult> {
    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN":
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ?? "",
            ...(data instanceof FormData
                ? {}
                : { "Content-Type": "application/json" }),
        },
        body: data instanceof FormData ? data : JSON.stringify(data),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.error) {
        const errors = Object.fromEntries(
            Object.entries(result.errors ?? {}).map(([field, value]) => [
                field,
                Array.isArray(value) ? String(value[0]) : String(value),
            ]),
        );
        throw new MemberRequestError(
            result.message ||
                (response.status === 419
                    ? "Your session expired. Reload this page after signing in again; keep your unsaved content safe."
                    : response.status === 401
                      ? "Sign in again before saving. Your changes are still here."
                      : "The request could not be completed."),
            errors,
        );
    }
    return result;
}
export function useMemberMutation() {
    const [busy, setBusy] = useState(false);
    const pending = useRef(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    async function run(
        url: string,
        data: FormData | Record<string, unknown>,
        method = "POST",
        saved?: (result: MutationResult) => void,
    ) {
        if (pending.current) return;
        pending.current = true;
        setBusy(true);
        setErrors({});
        try {
            const result = await memberRequest(url, data, method);
            notify(result.message || "Changes saved");
            saved?.(result);
        } catch (reason) {
            const error =
                reason instanceof MemberRequestError
                    ? reason
                    : new MemberRequestError(
                          "Unable to connect. Please try again.",
                      );
            setErrors({ ...error.errors, _request: error.message });
            notify(error.message, "error");
        } finally {
            pending.current = false;
            setBusy(false);
        }
    }
    return { busy, errors, setErrors, run };
}
export function useMemberDraft(dirty: boolean, busy = false) {
    const state = useRef({ dirty, busy });
    state.current = { dirty, busy };
    const t = useAdminTranslation();
    useEffect(() => {
        const unload = (event: BeforeUnloadEvent) => {
            if (state.current.dirty) event.preventDefault();
        };
        window.addEventListener("beforeunload", unload);
        const stop = router.on("before", (event) => {
            if (event.detail.visit.only.length) return;
            if (
                state.current.dirty &&
                !state.current.busy &&
                !window.confirm(t("You have unsaved changes. Leave this page?"))
            )
                return false;
        });
        return () => {
            window.removeEventListener("beforeunload", unload);
            stop();
        };
    }, [t]);
}
export function MemberErrors({ errors }: { errors: Record<string, string> }) {
    return Object.keys(errors).length ? (
        <div
            role="alert"
            className="space-y-1 rounded border border-destructive p-3 text-sm text-destructive"
        >
            <p>{errors._request || "Please check the highlighted fields."}</p>
            {Object.entries(errors)
                .filter(([key]) => key !== "_request")
                .map(([key, value]) => (
                    <p key={key}>{value}</p>
                ))}
        </div>
    ) : null;
}
export function MemberLayout({
    title,
    children,
    actions,
}: {
    title: string;
    children: ReactNode;
    actions?: ReactNode;
}) {
    const { member, urls } = useMember();
    const t = useAdminTranslation();
    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={title} />
            <a
                href="#member-content"
                className="sr-only focus:not-sr-only focus:block focus:p-3"
            >
                {t("Skip to content")}
            </a>
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <a href={urls.home} className="font-semibold">
                        {t("View website")}
                    </a>
                    <nav
                        aria-label={t("Account navigation")}
                        className="flex flex-wrap items-center gap-1"
                    >
                        <Button variant="ghost" asChild>
                            <Link href={urls.dashboard}>{t("Dashboard")}</Link>
                        </Button>
                        {urls.posts && (
                            <Button variant="ghost" asChild>
                                <Link href={urls.posts}>{t("Posts")}</Link>
                            </Button>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={urls.settings}>
                                {t("Account settings")}
                            </Link>
                        </Button>
                        <form action={urls.logout} method="post">
                            <input
                                type="hidden"
                                name="_token"
                                value={
                                    document.querySelector<HTMLMetaElement>(
                                        'meta[name="csrf-token"]',
                                    )?.content ?? ""
                                }
                            />
                            <Button variant="outline" type="submit">
                                {t("Sign out")}
                            </Button>
                        </form>
                    </nav>
                    <div className="flex items-center gap-2">
                        <img
                            src={member.avatar}
                            alt=""
                            className="size-8 rounded-full object-cover"
                        />
                        <span className="text-sm">{member.name}</span>
                    </div>
                </div>
            </header>
            <main
                id="member-content"
                className="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {actions}
                </div>
                {children}
            </main>
            <ToastHost />
        </div>
    );
}
