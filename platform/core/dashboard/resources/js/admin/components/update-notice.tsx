import { useAdminTranslation } from "@admin/lib/translate";
import axios from "axios";
import { useEffect, useState } from "react";

import { WidgetLink } from "./widget-link";
export function UpdateNotice({
    updater,
}: {
    updater?: { check: string; url: string } | null;
}) {
    const t = useAdminTranslation();
    const [message, setMessage] = useState("");
    useEffect(() => {
        setMessage("");
        if (!updater) return;
        const abort = new AbortController();
        axios
            .get(updater.check, { signal: abort.signal })
            .then((response) => {
                if (
                    !abort.signal.aborted &&
                    !response.data.error &&
                    response.data.data?.has_new_version
                )
                    setMessage(response.data.message);
            })
            .catch(() => {});
        return () => abort.abort();
    }, [updater]);
    return message && updater ? (
        <div
            role="status"
            className="mb-5 rounded-md border border-amber-400/50 bg-amber-400/10 p-4 text-sm"
        >
            {message}{" "}
            <WidgetLink url={updater.url}>{t("Update now")}</WidgetLink>
        </div>
    ) : null;
}
