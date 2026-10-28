import type { router } from "@inertiajs/react";

type VisitOptions = NonNullable<Parameters<typeof router.visit>[1]>;

export type AdminSession = {
    context: string;
    loginUrl: string;
    statusUrl: string;
};
let originalSession: AdminSession | null = null;

export function initializeAdminSession(
    session: AdminSession | null | undefined,
) {
    if (!originalSession && session?.context) originalSession = { ...session };
}
export function adminSession() {
    return originalSession;
}
export function draftContextHeaders(): Record<string, string> {
    return originalSession
        ? { "X-Admin-Draft-Context": originalSession.context }
        : {};
}
export function sameOriginUrl(url: string, origin: string): boolean {
    try {
        const target = new URL(url, origin);
        return target.origin === origin && !target.username && !target.password;
    } catch {
        return false;
    }
}
export function adminMutationUrl(url: string, origin: string): boolean {
    if (!originalSession || !sameOriginUrl(url, origin)) return false;
    const prefix = new URL(originalSession.statusUrl, origin).pathname.replace(
        /\/session\/status$/,
        "",
    );
    const path = new URL(url, origin).pathname;
    return path === prefix || path.startsWith(`${prefix}/`);
}
export function withoutStaleCsrf(data: unknown): unknown {
    if (typeof FormData !== "undefined" && data instanceof FormData) {
        data.delete("_token");
        return data;
    }
    if (
        data &&
        typeof data === "object" &&
        !Array.isArray(data) &&
        Object.hasOwn(data, "_token")
    ) {
        const { _token: _ignored, ...rest } = data as Record<string, unknown>;
        return rest;
    }
    return data;
}
export function draftVisitOptions(
    href: string,
    options: VisitOptions,
): VisitOptions {
    if (
        !originalSession ||
        !options.method ||
        ["get", "head"].includes(options.method) ||
        !adminMutationUrl(href, window.location.origin)
    )
        return {};
    return {
        headers: { ...options.headers, ...draftContextHeaders() },
        data: withoutStaleCsrf(options.data) as VisitOptions["data"],
    };
}
export function sessionRecovery(
    response: {
        status: number;
        headers: Record<string, string>;
        data: unknown;
    },
    session: AdminSession,
    origin: string,
): boolean {
    const data = response.data;
    return (
        response.status === 401 &&
        Object.entries(response.headers).some(
            ([key, value]) =>
                key.toLowerCase() === "x-admin-session-recovery" &&
                value === "1",
        ) &&
        !!data &&
        typeof data === "object" &&
        "code" in data &&
        data.code === "admin_session_expired" &&
        "loginUrl" in data &&
        data.loginUrl === session.loginUrl &&
        sameOriginUrl(session.loginUrl, origin) &&
        "statusUrl" in data &&
        data.statusUrl === session.statusUrl &&
        sameOriginUrl(session.statusUrl, origin)
    );
}
export function matchingSessionStatus(
    response: { ok: boolean },
    data: unknown,
): boolean {
    return (
        response.ok &&
        !!data &&
        typeof data === "object" &&
        "matches" in data &&
        data.matches === true
    );
}

export type SessionExceptionEvent = {
    defaultPrevented: boolean;
    preventDefault: () => void;
    detail: {
        response: {
            status: number;
            headers: Record<string, string>;
            data: unknown;
        };
    };
};
export function handleSessionException(
    event: SessionExceptionEvent,
    session: AdminSession,
    origin: string,
    expired: () => void,
    forbidden: () => void,
    failed: () => void = () => {},
): void {
    // Inertia cancels an HTTP exception when a listener returns false.
    // Only explicitly handled recovery responses should prevent default handling.
    if (event.defaultPrevented) return;
    const response = event.detail.response;
    if (sessionRecovery(response, session, origin)) {
        event.preventDefault();
        expired();
        return;
    }
    if (
        response.status === 403 &&
        Object.entries(response.headers).some(
            ([key, value]) =>
                key.toLowerCase() === "x-admin-draft-forbidden" &&
                value === "1",
        )
    ) {
        event.preventDefault();
        forbidden();
        return;
    }
    if (
        response.status >= 500 &&
        Object.entries(response.headers).some(
            ([key, value]) =>
                key.toLowerCase() === "x-admin-draft-error" && value === "1",
        )
    ) {
        event.preventDefault();
        failed();
        return;
    }
}
export function announceAdminResponse(
    response: SessionExceptionEvent["detail"]["response"],
) {
    if (
        originalSession &&
        (sessionRecovery(response, originalSession, window.location.origin) ||
            response.headers["x-admin-draft-forbidden"] === "1")
    ) {
        document.dispatchEvent(
            new CustomEvent("admin:session-exception", {
                cancelable: true,
                detail: { response },
            }),
        );
    }
}
export async function checkAdminFetchResponse(response: Response) {
    if (
        response.status === 401 &&
        response.headers.get("X-Admin-Session-Recovery") === "1"
    ) {
        announceAdminResponse({
            status: response.status,
            headers: Object.fromEntries(response.headers),
            data: await response.clone().json(),
        });
    } else if (
        response.status === 403 &&
        response.headers.get("X-Admin-Draft-Forbidden") === "1"
    ) {
        announceAdminResponse({
            status: response.status,
            headers: Object.fromEntries(response.headers),
            data: null,
        });
    }
}

export function currentCsrfHeaders(
    cookie = typeof document === "undefined" ? "" : document.cookie,
    meta = typeof document === "undefined"
        ? ""
        : (document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
              ?.content ?? ""),
    targetUrl = typeof window === "undefined" ? "" : window.location.href,
): Record<string, string> {
    const origin =
        typeof window === "undefined"
            ? "http://localhost"
            : window.location.origin;
    if (!sameOriginUrl(targetUrl, origin)) return {};
    const context = adminMutationUrl(targetUrl, origin)
        ? draftContextHeaders()
        : {};
    const xsrf = cookie
        .split(";")
        .map((value) => value.trim())
        .find((value) => value.startsWith("XSRF-TOKEN="));
    if (xsrf) {
        try {
            const value = decodeURIComponent(xsrf.slice("XSRF-TOKEN=".length));
            if (value) return { ...context, "X-XSRF-TOKEN": value };
        } catch {
            /* An invalid cookie must not prevent the normal CSRF fallback. */
        }
    }
    return { ...context, ...(meta ? { "X-CSRF-TOKEN": meta } : {}) };
}
