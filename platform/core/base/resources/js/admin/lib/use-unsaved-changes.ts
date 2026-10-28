import { requestConfirmation } from "@admin/components/confirm-dialog";
import { draftSnapshot, isSchemaRefresh } from "./draft-state";
import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { useAdminTranslation } from "./translate";

export function useUnsavedChanges(dirty: boolean, _processing = false) {
    const t = useAdminTranslation();
    useEffect(() => {
        if (!dirty) return;
        const unload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", unload);
        let active = true;
        let resuming = false;
        const stop = router.on("before", (event) => {
            if (
                resuming ||
                event.detail.visit.method !== "get" ||
                isSchemaRefresh(event.detail.visit, window.location.href)
            )
                return;
            event.preventDefault();
            const visit = event.detail.visit;
            void requestConfirmation(t("Discard unsaved changes?")).then(
                (accepted) => {
                    if (!active || !accepted) return;
                    resuming = true;
                    router.visit(visit.url, visit);
                    resuming = false;
                },
            );
        });
        return () => {
            active = false;
            window.removeEventListener("beforeunload", unload);
            stop();
        };
    }, [dirty, t]);
}

export function useDraftBaseline(value: unknown) {
    const serialized = draftSnapshot(value);
    const [baseline, setBaseline] = useState(serialized);
    return {
        dirty: serialized !== baseline,
        saved: (nextValue: unknown = value) =>
            setBaseline(draftSnapshot(nextValue)),
    };
}
