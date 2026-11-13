import { useEffect, useState } from "react";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { SafeRichText } from "@admin/components/safe-rich-text";
import { TablePagination } from "@admin/components/table-pagination";
import { Button } from "@admin/components/ui/button";
import { useAdminTranslation } from "@admin/lib/translate";
import { MemberLayout, useMember } from "./shared";
type Activity = {
    data: { id: number; description: string; ip_address: string }[];
    meta?: {
        total: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    links?: { prev?: string | null; next?: string | null };
};
export default function Dashboard({
    statistics,
    extraStatistics,
}: {
    statistics: { label: string; count: number }[];
    extraStatistics?: string | null;
}) {
    const { urls } = useMember();
    const t = useAdminTranslation();
    const [url, setUrl] = useState(urls.activity);
    const [attempt, setAttempt] = useState(0);
    const [activity, setActivity] = useState<Activity>({ data: [] });
    const [busy, setBusy] = useState(true);
    const [error, setError] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        setBusy(true);
        setError("");
        fetch(url, {
            signal: controller.signal,
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        })
            .then(async (response) => {
                if (!response.ok) throw new Error();
                return response.json();
            })
            .then((result) => {
                if (!controller.signal.aborted) setActivity(result);
            })
            .catch(() => {
                if (!controller.signal.aborted)
                    setError(t("Activity could not load. Please try again."));
            })
            .finally(() => {
                if (!controller.signal.aborted) setBusy(false);
            });
        return () => controller.abort();
    }, [url, attempt, t]);
    return (
        <MemberLayout title={t("Dashboard")}>
            {extraStatistics && <SafeRichText html={extraStatistics} />}
            <div className="grid gap-4 sm:grid-cols-3">
                {statistics.map((item) => (
                    <Card key={item.label}>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                {item.label}
                            </p>
                            <p className="mt-2 text-3xl font-semibold tabular-nums">
                                {item.count}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>{t("Activity logs")}</CardTitle>
                </CardHeader>
                <CardContent aria-busy={busy}>
                    {error ? (
                        <div role="alert" className="space-y-3">
                            <p>{error}</p>
                            <Button
                                onClick={() => setAttempt((value) => value + 1)}
                            >
                                {t("Retry")}
                            </Button>
                        </div>
                    ) : busy ? (
                        <p role="status">{t("Loading…")}</p>
                    ) : activity.data.length ? (
                        <ul className="divide-y">
                            {activity.data.map((item) => (
                                <li key={item.id} className="py-3">
                                    <SafeRichText html={item.description} />
                                    <span className="text-xs text-muted-foreground">
                                        {item.ip_address}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p>{t("No activity yet")}</p>
                    )}
                </CardContent>
                <TablePagination
                    total={activity.meta?.total ?? 0}
                    currentPage={activity.meta?.current_page}
                    lastPage={activity.meta?.last_page}
                    previous={activity.links?.prev}
                    next={activity.links?.next}
                    loading={busy}
                    onVisit={setUrl}
                />
            </Card>
        </MemberLayout>
    );
}
