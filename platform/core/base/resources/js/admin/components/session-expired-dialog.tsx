import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import {
    adminSession,
    draftContextHeaders,
    matchingSessionStatus,
    handleSessionException,
    type SessionExceptionEvent,
} from "@admin/lib/admin-session";
import { useAdminTranslation } from "@admin/lib/translate";
import { Dialog } from "./ui/dialog";
import { Button } from "./ui/button";

export function SessionExpiredDialog({
    showError,
}: {
    showError: (message: string) => void;
}) {
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const session = adminSession();
    useEffect(() => {
        if (!session) return;
        const handle = (event: SessionExceptionEvent) =>
            handleSessionException(
                event,
                session,
                window.location.origin,
                () => {
                    setError("");
                    setOpen(true);
                },
                () =>
                    showError(
                        t(
                            "You do not have permission to perform this action. Your changes have not been saved.",
                        ),
                    ),
                () =>
                    showError(
                        t(
                            "The server could not save your changes. Your draft is still here. Please try again later.",
                        ),
                    ),
            );
        const stop = router.on("httpException", handle);
        const onAjax = (event: Event) =>
            handle(event as CustomEvent<SessionExceptionEvent["detail"]>);
        document.addEventListener("admin:session-exception", onAjax);
        return () => {
            stop();
            document.removeEventListener("admin:session-exception", onAjax);
        };
    }, [session, showError, t]);
    async function resume() {
        if (!session || busy) return;
        setBusy(true);
        setError("");
        try {
            const response = await fetch(session.statusUrl, {
                headers: {
                    Accept: "application/json",
                    ...draftContextHeaders(),
                },
                credentials: "same-origin",
                cache: "no-store",
                redirect: "error",
            });
            if (!matchingSessionStatus(response, await response.json()))
                throw new Error();
            setOpen(false);
        } catch {
            setError(
                t(
                    "Sign in with the original account before continuing. Keep this tab open to preserve your changes.",
                ),
            );
        } finally {
            setBusy(false);
        }
    }
    if (!session) return null;
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!value) void resume();
            }}
            title={t("Your session has expired")}
            description={t(
                "Your changes are still in this tab and have not been saved. Sign in again with the same account in a new tab, then return here.",
            )}
            size="sm"
            footer={
                <>
                    <Button asChild variant="outline">
                        <a
                            href={session.loginUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t("Sign in in a new tab")}
                        </a>
                    </Button>
                    <Button loading={busy} onClick={() => void resume()}>
                        {t("I have signed in")}
                    </Button>
                </>
            }
        >
            <p className="text-sm text-muted-foreground">
                {t(
                    "After your account is verified, press Save again yourself. Do not refresh or close this tab.",
                )}
            </p>
            {error && (
                <p role="alert" className="mt-3 text-sm text-destructive">
                    {error}
                </p>
            )}
        </Dialog>
    );
}
