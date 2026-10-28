import { isMenuReferences, requirePayload } from "@admin/lib/admin-api";
import { useAdminTranslation } from "@admin/lib/translate";
import axios from "axios";
import { useEffect, useState } from "react";
import type { ReferencePage } from "../Menu/Form";
export function useMenuReferences(
    initial: ReferencePage,
    url: string,
    menuId?: string | number | null,
    languageCode?: string,
) {
    const t = useAdminTranslation();
    const [search, setSearch] = useState("");
    const [referenceType, setReferenceType] = useState(initial.type);
    const [referenceResults, setReferenceResults] = useState(initial);
    const [referenceNumber, setReferenceNumber] = useState(1);
    const [referencesLoading, setReferencesLoading] = useState(false);
    const [referencesError, setReferencesError] = useState("");
    const [retryReferences, setRetryReferences] = useState(0);
    useEffect(() => {
        if (!menuId || !referenceType) return;
        const controller = new AbortController();
        setReferencesLoading(true);
        setReferencesError("");
        const timeout = window.setTimeout(() => {
            axios
                .get<ReferencePage>(url, {
                    params: {
                        type: referenceType,
                        search,
                        page: referenceNumber,
                        ref_lang: languageCode,
                    },
                    signal: controller.signal,
                })
                .then(({ data }) => {
                    if (!controller.signal.aborted)
                        setReferenceResults(
                            requirePayload<ReferencePage>(
                                data,
                                isMenuReferences,
                            ),
                        );
                })
                .catch((error) => {
                    if (!controller.signal.aborted && !axios.isCancel(error))
                        setReferencesError(
                            t("Unable to load content. Please try again."),
                        );
                })
                .finally(() => {
                    if (!controller.signal.aborted) setReferencesLoading(false);
                });
        }, 250);
        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [
        menuId,
        referenceType,
        search,
        referenceNumber,
        url,
        languageCode,
        retryReferences,
        t,
    ]);

    return {
        search,
        setSearch,
        referenceType,
        setReferenceType,
        referenceResults,
        referenceNumber,
        setReferenceNumber,
        referencesLoading,
        referencesError,
        setRetryReferences,
    };
}
