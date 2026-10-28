import { notify } from "@admin/components/toast";
import { assertAdminResult } from "@admin/lib/admin-result";
import { createLatestRequest } from "@admin/lib/latest-request";
import { useAdminTranslation } from "@admin/lib/translate";
import axios from "axios";
import { useEffect, useState } from "react";
import type { Widget, WidgetData } from "../lib/dashboard-types";
export function useDashboardWidget(widget: Widget, settingUrl: string) {
    const t = useAdminTranslation();
    const [settingsRequest] = useState(createLatestRequest);
    const [range, setRange] = useState(
        widget.ranges?.find(
            (item) => item.key === widget.settings?.predefined_range,
        )?.key ??
            widget.ranges?.[0]?.key ??
            "",
    );
    const [page, setPage] = useState(1);
    const [revision, setRevision] = useState(0);
    const [data, setData] = useState<WidgetData | null>(null);
    const [error, setError] = useState("");
    const [savingRange, setSavingRange] = useState(false);
    useEffect(() => {
        settingsRequest.cancel();
        setSavingRange(false);
        return () => settingsRequest.cancel();
    }, [settingsRequest, widget.name, widget.url, settingUrl]);
    useEffect(() => {
        const abort = new AbortController();
        setData(null);
        setError("");
        axios
            .get(widget.url, {
                params: {
                    admin_json: 1,
                    page,
                    ...(range ? { predefined_range: range } : {}),
                },
                signal: abort.signal,
            })
            .then((response) => {
                if (abort.signal.aborted) return;
                if (response.data.error && response.data.data?.configuration) {
                    setData(response.data.data);
                    return;
                }
                assertAdminResult(response.data, t("Something went wrong"));
                if (
                    !response.data.data ||
                    typeof response.data.data !== "object"
                )
                    throw new Error(
                        t("This widget requires the Blade renderer."),
                    );
                setData(response.data.data);
            })
            .catch((error) => {
                if (!abort.signal.aborted) setError(error.message);
            });
        return () => abort.abort();
    }, [widget.url, range, page, revision, t]);
    async function changeRange(value: string) {
        setSavingRange(true);
        await settingsRequest.run(
            (signal) =>
                axios
                    .post(
                        settingUrl,
                        {
                            name: widget.name,
                            setting_name: "predefined_range",
                            setting_value: value,
                        },
                        { signal },
                    )
                    .then((response) => {
                        assertAdminResult(
                            response.data,
                            t("Could not save date range"),
                        );
                        return response;
                    }),
            {
                onSuccess: () => {
                    setRange(value);
                    setPage(1);
                },
                onError: (error) =>
                    notify(
                        error instanceof Error
                            ? error.message
                            : t("Could not save date range"),
                        true,
                    ),
                onSettled: () => setSavingRange(false),
            },
        );
    }
    return {
        range,
        page,
        setPage,
        setRevision,
        data,
        error,
        savingRange,
        changeRange,
    };
}
