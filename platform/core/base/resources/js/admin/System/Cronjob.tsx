import { useAdminDate } from "@admin/lib/date";
import { Head } from "@inertiajs/react";
import { useState } from "react";
import { Copy, CheckCircle2, AlertCircle, Clock } from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
} from "@admin/components/ui/card";
import { Button } from "@admin/components/ui/button";
import { notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";

export default function Cronjob({
    title,
    description,
    command,
    lastRunAt,
    status,
    statusMessage,
    setupTitle,
    steps,
}: {
    title: string;
    description: string;
    command: string;
    lastRunAt: string | null;
    status: "healthy" | "stale" | "unconfigured";
    statusMessage: string;
    setupTitle: string;
    steps: string[];
}) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    const [copied, setCopied] = useState(false);
    const StatusIcon =
        status === "healthy"
            ? CheckCircle2
            : status === "stale"
              ? AlertCircle
              : Clock;
    async function copy() {
        try {
            await navigator.clipboard.writeText(command);
            setCopied(true);
            notify(t("Copied"));
        } catch {
            notify(
                t("Could not copy. Select and copy the text manually."),
                true,
            );
        }
    }
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="max-w-4xl space-y-5">
                <p className="text-sm text-muted-foreground">{description}</p>
                <Card>
                    <CardHeader>
                        <CardTitle>{t("Cron command")}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <textarea
                                aria-label={t("Cron command")}
                                readOnly
                                value={command}
                                rows={3}
                                className="w-full min-w-0 rounded-md border bg-muted/40 p-3 font-mono text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                onFocus={(event) => event.target.select()}
                            />
                            <Button
                                variant="outline"
                                onClick={() => void copy()}
                            >
                                <Copy className="size-4" />
                                {t(copied ? "Copied" : "Copy")}
                            </Button>
                        </div>
                        <div
                            role="status"
                            className={`flex items-start gap-3 rounded-md border p-4 ${status === "stale" ? "border-destructive/30 text-destructive" : status === "healthy" ? "border-primary/30 text-primary-ink" : "text-muted-foreground"}`}
                        >
                            <StatusIcon className="mt-0.5 size-5 shrink-0" />
                            <div className="space-y-1 text-sm">
                                <p className="font-medium">{statusMessage}</p>
                                {lastRunAt && (
                                    <p>
                                        {t("Last run")}:{" "}
                                        <time dateTime={lastRunAt}>
                                            {formatDate(lastRunAt, true)}
                                        </time>
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>{setupTitle}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="list-decimal space-y-3 ps-5 text-sm leading-relaxed">
                            {steps.map((step, index) => (
                                <li key={index}>{step}</li>
                            ))}
                        </ol>
                        <a
                            className="mt-5 inline-block text-sm text-primary-ink underline underline-offset-4"
                            href="https://laravel.com/docs/13.x/scheduling"
                            target="_blank"
                            rel="noreferrer"
                        >
                            {t("Documentation")}
                        </a>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
