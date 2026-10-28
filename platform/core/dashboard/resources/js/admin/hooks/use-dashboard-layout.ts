import { notify } from "@admin/components/toast";
import { assertAdminResult } from "@admin/lib/admin-result";
import { createLatestRequest } from "@admin/lib/latest-request";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import { router } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useRef, useState } from "react";
import { reorderDashboardWidgets } from "../lib/dashboard-order";
import type { Widget } from "../lib/dashboard-types";
export function useDashboardLayout(
    widgets: Widget[],
    urls: { order: string; visibility: string },
) {
    const t = useAdminTranslation();
    const [manage, setManage] = useState(false),
        [items, setItems] = useState(widgets),
        [display, setDisplay] = useState(widgets),
        [busy, setBusy] = useState(false),
        [error, setError] = useState("");
    const [request] = useState(createLatestRequest);
    const ordering = useRef(false);
    useEffect(() => {
        request.cancel();
        ordering.current = false;
        setBusy(false);
        setItems(widgets);
        setDisplay(widgets);
        return () => request.cancel();
    }, [widgets, urls.order, urls.visibility, request]);
    async function save() {
        setBusy(true);
        setError("");
        await request.run(
            (signal) =>
                axios
                    .post(
                        urls.visibility,
                        {
                            widgets: Object.fromEntries(
                                items.map((item) => [
                                    item.name,
                                    item.visible ? 1 : 0,
                                ]),
                            ),
                        },
                        { signal },
                    )
                    .then((response) => {
                        assertAdminResult(
                            response.data,
                            t("Something went wrong"),
                        );
                        return response;
                    }),
            {
                onSuccess: (response) => {
                    setManage(false);
                    notify(response.data.message || t("Saved successfully"));
                    router.reload();
                },
                onError: (error) =>
                    setError(
                        error instanceof Error
                            ? error.message
                            : t("Something went wrong"),
                    ),
                onSettled: () => setBusy(false),
            },
        );
    }
    async function move(source: string, target: string, after?: boolean) {
        if (ordering.current) return;
        const next = reorderDashboardWidgets(display, source, target, after);
        if (!next) return;
        const previous = display;
        ordering.current = true;
        setBusy(true);
        setDisplay(next.display);
        await request.run(
            (signal) =>
                axios
                    .post(urls.order, { items: next.names }, { signal })
                    .then((response) => {
                        assertAdminResult(
                            response.data,
                            t("Something went wrong"),
                        );
                        return response;
                    }),
            {
                onSuccess: (response) =>
                    notify(response.data.message || t("Saved successfully")),
                onError: (error) => {
                    setDisplay(previous);
                    notify(
                        error instanceof Error
                            ? error.message
                            : t("Something went wrong"),
                        true,
                    );
                },
                onSettled: () => {
                    ordering.current = false;
                    setBusy(false);
                },
            },
        );
    }
    const sort = usePointerSort((source, destination) => {
        const [target, after] = JSON.parse(destination) as [string, boolean];
        void move(source, target, after);
    });
    return {
        manage,
        setManage,
        items,
        setItems,
        display,
        busy,
        error,
        setError,
        save,
        move,
        sort,
    };
}
