import { useEffect, useRef, useState } from "react";
import { Dialog } from "./ui/dialog";
import { Button } from "./ui/button";
import { useAdminTranslation } from "@admin/lib/translate";

export function useConfirmDialog() {
    const t = useAdminTranslation();
    const [message, setMessage] = useState<string | null>(null);
    const resolve = useRef<((value: boolean) => void) | null>(null);
    const cancel = useRef<HTMLButtonElement>(null);
    useEffect(
        () => () => {
            resolve.current?.(false);
        },
        [],
    );
    function finish(value: boolean) {
        resolve.current?.(value);
        resolve.current = null;
        setMessage(null);
    }
    return {
        confirm: (next: string) =>
            new Promise<boolean>((done) => {
                resolve.current?.(false);
                resolve.current = done;
                setMessage(next);
            }),
        confirmationDialog: (
            <Dialog
                open={message !== null}
                onOpenChange={(open) => {
                    if (!open) finish(false);
                }}
                title={t("Confirm")}
                description={message ?? undefined}
                size="sm"
                initialFocusRef={cancel}
                footer={
                    <>
                        <Button
                            ref={cancel}
                            variant="outline"
                            onClick={() => finish(false)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button onClick={() => finish(true)}>
                            {t("Continue")}
                        </Button>
                    </>
                }
            >
                <span />
            </Dialog>
        ),
    };
}

type ConfirmationRequest = {
    message: string;
    resolve: (accepted: boolean) => void;
};
let confirmationListener: ((request: ConfirmationRequest) => void) | null =
    null;
export function requestConfirmation(message: string): Promise<boolean> {
    return new Promise((resolve) => {
        if (confirmationListener) confirmationListener({ message, resolve });
        else resolve(false);
    });
}
export function ConfirmHost() {
    const { confirm, confirmationDialog } = useConfirmDialog();
    const confirmRef = useRef(confirm);
    confirmRef.current = confirm;
    useEffect(() => {
        const listener = (request: ConfirmationRequest) => {
            void confirmRef.current(request.message).then(request.resolve);
        };
        confirmationListener = listener;
        return () => {
            if (confirmationListener === listener) confirmationListener = null;
        };
    }, []);
    return confirmationDialog;
}
