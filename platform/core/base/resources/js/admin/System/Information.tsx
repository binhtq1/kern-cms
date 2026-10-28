import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import { Head, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import axios from "axios";
import { Check, X, Copy } from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
} from "@admin/components/ui/card";
import { Button } from "@admin/components/ui/button";
import { LoadingIndicator } from "@admin/components/loading-indicator";
import { Dialog } from "@admin/components/ui/dialog";
import { notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";

type EnvironmentRow = {
    key: string;
    label: string;
    value: string | number | boolean | null;
};
export default function Information({
    title,
    application,
    server,
    packages,
    requiredPhpVersion,
    phpRequirementSatisfied,
    urls,
    labels,
}: {
    title: string;
    application: EnvironmentRow[];
    server: EnvironmentRow[];
    packages: { name: string; version: string; dependencies: string }[];
    requiredPhpVersion: string;
    phpRequirementSatisfied: boolean;
    urls: { additionalData: string; disableDebug: string | null };
    labels: Record<string, string>;
}) {
    const t = useAdminTranslation();
    const [appSize, setAppSize] = useState<string | null>(null);
    const [sizeError, setSizeError] = useState(false);
    const [sizeAttempt, setSizeAttempt] = useState(0);
    const [reportVisible, setReportVisible] = useState(false);
    const [confirmDebug, setConfirmDebug] = useState(false);
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        const abort = new AbortController();
        setSizeError(false);
        axios
            .get(urls.additionalData, { signal: abort.signal })
            .then(({ data }) => {
                if (data.error || typeof data.data?.appSize !== "string")
                    throw new Error();
                setAppSize(data.data.appSize);
            })
            .catch(() => {
                if (!abort.signal.aborted) setSizeError(true);
            });
        return () => abort.abort();
    }, [urls.additionalData, sizeAttempt]);
    const appRows = application.map((row) =>
        row.key === "app_size"
            ? {
                  ...row,
                  value: appSize ?? (sizeError ? t("Unavailable") : null),
              }
            : row,
    );
    const textValue = (value: EnvironmentRow["value"]) =>
        typeof value === "boolean"
            ? t(value ? "Yes" : "No")
            : String(value ?? "—");
    const report = [
        `### ${labels.system_environment}`,
        ...appRows.map((row) => `- ${row.label}: ${textValue(row.value)}`),
        "",
        `### ${labels.server_environment}`,
        ...server.map((row) => `- ${row.label}: ${textValue(row.value)}`),
        `- ${t("Required PHP version")}: ${requiredPhpVersion}`,
        `- ${t("PHP requirement satisfied")}: ${textValue(phpRequirementSatisfied)}`,
        "",
        `### ${labels.installed_packages}`,
        ...packages.map((item) => `- ${item.name}: ${item.version}`),
    ].join("\n");
    async function copy(text: string) {
        try {
            await navigator.clipboard.writeText(text);
            notify(t("Copied"));
        } catch {
            notify(
                t("Could not copy. Select and copy the text manually."),
                true,
            );
        }
    }
    async function disableDebug() {
        if (!urls.disableDebug) return;
        setBusy(true);
        try {
            const { data } = await axios.post(urls.disableDebug);
            if (data.error) throw new Error(data.message);
            notify(data.message || t("Saved"));
            setConfirmDebug(false);
            router.reload();
        } catch (error) {
            notify(
                axios.isAxiosError(error)
                    ? error.response?.data?.message || error.message
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
                true,
            );
        } finally {
            setBusy(false);
        }
    }
    const environment = (rows: EnvironmentRow[], heading: string) => (
        <Card>
            <CardHeader>
                <CardTitle>{heading}</CardTitle>
            </CardHeader>
            <CardContent className="py-1">
                <dl className="divide-y">
                    {rows.map((row) => (
                        <div
                            key={row.key}
                            className="grid grid-cols-2 gap-3 py-3 text-sm"
                        >
                            <dt className="text-muted-foreground">
                                {row.label}
                            </dt>
                            <dd className="break-words text-end">
                                {row.key === "app_size" &&
                                appSize === null &&
                                !sizeError ? (
                                    <LoadingIndicator
                                        label={`${row.label}: ${t("Loading…")}`}
                                    />
                                ) : typeof row.value === "boolean" ? (
                                    <span
                                        className={`inline-flex items-center gap-1 ${row.value ? "text-primary-ink" : "text-destructive"}`}
                                    >
                                        {row.value ? (
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {textValue(row.value)}
                                    </span>
                                ) : (
                                    <span
                                        className={
                                            row.key === "server_ip"
                                                ? "select-all"
                                                : undefined
                                        }
                                    >
                                        {textValue(row.value)}
                                    </span>
                                )}
                                {row.key === "server_ip" &&
                                    typeof row.value === "string" && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="ml-2"
                                            onClick={() =>
                                                void copy(String(row.value))
                                            }
                                        >
                                            <Copy
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {t("Copy IP")}
                                        </Button>
                                    )}
                                {row.key === "app_size" && sizeError && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setSizeAttempt(
                                                (attempt) => attempt + 1,
                                            )
                                        }
                                    >
                                        {t("Retry")}
                                    </Button>
                                )}
                                {row.key === "php_version" &&
                                    !phpRequirementSatisfied && (
                                        <span className="mt-1 block text-xs text-destructive">
                                            {t("PHP must be >= :version", {
                                                version: requiredPhpVersion,
                                            })}
                                        </span>
                                    )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
    return (
        <AdminLayout
            title={title}
            actions={
                urls.disableDebug && (
                    <Button
                        variant="outline"
                        onClick={() => setConfirmDebug(true)}
                    >
                        {t("Disable debug mode")}
                    </Button>
                )
            }
        >
            <Head title={title} />
            <div className="space-y-5">
                <Card>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {labels.report_description}
                        </p>
                        <Button
                            variant="outline"
                            aria-expanded={reportVisible}
                            aria-controls="system-report"
                            onClick={() =>
                                setReportVisible((visible) => !visible)
                            }
                        >
                            {labels.get_system_report}
                        </Button>
                        {reportVisible && (
                            <div id="system-report" className="space-y-3">
                                <textarea
                                    aria-label={labels.get_system_report}
                                    readOnly
                                    rows={12}
                                    value={report}
                                    onFocus={(event) => event.target.select()}
                                    className="w-full rounded-md border bg-muted/30 p-3 font-mono text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <Button
                                    variant="outline"
                                    onClick={() => void copy(report)}
                                >
                                    <Copy className="size-4" />
                                    {labels.copy_report}
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <div className="grid items-start gap-5 xl:grid-cols-3">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>{labels.installed_packages}</CardTitle>
                        </CardHeader>
                        <div className="overflow-x-auto">
                            <Table className="w-full text-left text-sm">
                                <TableHeader className="border-b bg-muted/40">
                                    <TableRow>
                                        {[
                                            labels.package_name,
                                            labels.version,
                                            labels.dependency_name,
                                        ].map((label) => (
                                            <TableHead
                                                key={label}
                                                className="px-4 py-3 font-medium"
                                            >
                                                {label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {packages.map((item) => (
                                        <TableRow key={item.name}>
                                            <TableHead
                                                scope="row"
                                                className="px-4 py-3 font-medium"
                                            >
                                                {item.name}
                                            </TableHead>
                                            <TableCell className="px-4 py-3">
                                                {item.version}
                                            </TableCell>
                                            <TableCell className="min-w-48 break-words px-4 py-3 text-muted-foreground">
                                                {item.dependencies}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>
                    <div className="space-y-5">
                        {environment(appRows, labels.system_environment)}
                        {environment(server, labels.server_environment)}
                    </div>
                </div>
            </div>
            <Dialog
                open={confirmDebug}
                onOpenChange={(open) => {
                    if (!busy) setConfirmDebug(open);
                }}
                title={t("Disable debug mode")}
                description={t("Disable application debug mode?")}
            >
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setConfirmDebug(false)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button loading={busy} onClick={() => void disableDebug()}>
                        {t("Disable debug mode")}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
