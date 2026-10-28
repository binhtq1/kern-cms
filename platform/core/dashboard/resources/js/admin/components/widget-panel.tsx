import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Select } from "@admin/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@admin/components/ui/table";
import { useAdminDate } from "@admin/lib/date";
import { useAdminTranslation } from "@admin/lib/translate";
import { ChartNoAxesCombined } from "lucide-react";
import { type ReactNode } from "react";
import { DeferredAnalytics } from "../components/deferred-analytics";

import { useDashboardWidget } from "../hooks/use-dashboard-widget";
import type { Widget } from "../lib/dashboard-types";
import { WidgetLink, safeUrl } from "./widget-link";
import { WidgetLoading } from "./widget-loading";
function formatMetric(value: string | number) {
    return String(value).trim() !== "" && Number.isFinite(Number(value))
        ? Number(value).toLocaleString()
        : value;
}
export function WidgetPanel({
    widget,
    settingUrl,
    controls,
}: {
    widget: Widget;
    settingUrl: string;
    controls: ReactNode;
}) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    const {
        range,
        setPage,
        setRevision,
        data,
        error,
        savingRange,
        changeRange,
    } = useDashboardWidget(widget, settingUrl);
    return (
        <Card className="min-w-0 h-full">
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                    {controls}
                    <CardTitle>{widget.title}</CardTitle>
                </div>
                {!!widget.ranges?.length && (
                    <Select
                        aria-label={`${widget.title}: ${t("Date range")}`}
                        value={range}
                        disabled={savingRange || Boolean(data?.configuration)}
                        onValueChange={(value) => void changeRange(value)}
                    >
                        {widget.ranges.map((item) => (
                            <option key={item.key} value={item.key}>
                                {item.label}
                            </option>
                        ))}
                    </Select>
                )}
            </CardHeader>
            <CardContent
                className="min-h-48 overflow-x-auto"
                aria-busy={!data && !error}
            >
                {error ? (
                    <div role="alert" className="space-y-3">
                        <p className="text-sm text-destructive">{error}</p>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setRevision((old) => old + 1)}
                        >
                            {t("Retry")}
                        </Button>
                    </div>
                ) : !data ? (
                    <WidgetLoading
                        label={`${widget.title}: ${t("Loading…")}`}
                    />
                ) : data.configuration ? (
                    <div
                        role="status"
                        className="flex min-h-48 flex-col items-center justify-center gap-3 px-3 py-6 text-center"
                    >
                        <ChartNoAxesCombined
                            aria-hidden="true"
                            className="size-8 text-muted-foreground"
                        />
                        <div className="space-y-1.5">
                            <h3 className="text-sm font-semibold">
                                {data.configuration.title}
                            </h3>
                            <p className="max-w-lg text-sm leading-6 text-muted-foreground">
                                {data.configuration.description}
                            </p>
                        </div>
                        {data.configuration.url && (
                            <Button variant="outline" asChild>
                                <a href={data.configuration.url}>
                                    {t("Configure Google Analytics")}
                                </a>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        {(data.chart || data.countries) && (
                            <DeferredAnalytics
                                chart={data.chart}
                                countries={data.countries}
                            />
                        )}
                        {data.stats && (
                            <dl className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                                {Object.entries(data.stats).map(
                                    ([label, value]) => (
                                        <div
                                            key={label}
                                            className="rounded-md border p-3"
                                        >
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="mt-1 text-xl font-semibold tabular-nums">
                                                {formatMetric(value)}
                                            </dd>
                                        </div>
                                    ),
                                )}
                            </dl>
                        )}
                        {!!data.records?.length &&
                            (data.records.some(
                                (record) => record.action || record.user,
                            ) ? (
                                <ul className="divide-y">
                                    {data.records.map((record, index) => (
                                        <li
                                            key={index}
                                            className="flex gap-3 py-3"
                                        >
                                            {safeUrl(record.avatar) && (
                                                <img
                                                    src={safeUrl(record.avatar)}
                                                    alt=""
                                                    className="size-9 shrink-0 rounded-full object-cover"
                                                />
                                            )}
                                            <div className="min-w-0 text-sm">
                                                <p>
                                                    <strong>
                                                        {record.user}
                                                    </strong>{" "}
                                                    {record.action ||
                                                        record.title}{" "}
                                                    {record.module && (
                                                        <span className="font-medium">
                                                            {record.module}
                                                        </span>
                                                    )}{" "}
                                                    {record.reference_name && (
                                                        <span className="font-medium">
                                                            {
                                                                record.reference_name
                                                            }
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {record.ip_address && (
                                                        <span>
                                                            <WidgetLink
                                                                url={`https://ipinfo.io/${encodeURIComponent(record.ip_address)}`}
                                                                target="_blank"
                                                            >
                                                                {
                                                                    record.ip_address
                                                                }
                                                            </WidgetLink>{" "}
                                                            ·{" "}
                                                        </span>
                                                    )}
                                                    <time
                                                        dateTime={
                                                            record.created_at
                                                        }
                                                        title={
                                                            record.created_at
                                                                ? formatDate(
                                                                      record.created_at,
                                                                      true,
                                                                  )
                                                                : undefined
                                                        }
                                                    >
                                                        {record.relative_time ||
                                                            (record.created_at
                                                                ? formatDate(
                                                                      record.created_at,
                                                                      true,
                                                                  )
                                                                : "")}
                                                    </time>
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <Table className="w-full text-left text-sm">
                                    <TableHeader className="border-b">
                                        <TableRow>
                                            <TableHead
                                                scope="col"
                                                className="p-2"
                                            >
                                                #
                                            </TableHead>
                                            <TableHead
                                                scope="col"
                                                className="p-2"
                                            >
                                                {data.columns?.find(
                                                    (column) =>
                                                        column.key === "title",
                                                )?.label ?? t("Title")}
                                            </TableHead>
                                            <TableHead
                                                scope="col"
                                                className="p-2"
                                            >
                                                {data.records.some(
                                                    (record) =>
                                                        record.status_code !==
                                                        undefined,
                                                )
                                                    ? t("Status code")
                                                    : t("Created at")}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="divide-y">
                                        {data.records.map((record, index) => (
                                            <TableRow key={index}>
                                                <TableCell className="p-2 text-muted-foreground">
                                                    {record.index ?? index + 1}
                                                </TableCell>
                                                <TableCell className="p-2 break-words">
                                                    <WidgetLink
                                                        url={record.url}
                                                        target={record.target}
                                                    >
                                                        {record.title}
                                                    </WidgetLink>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap p-2 text-muted-foreground">
                                                    {record.status_code ??
                                                        (record.created_at
                                                            ? formatDate(
                                                                  record.created_at,
                                                              )
                                                            : "")}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ))}
                        {!!data.rows?.length && (
                            <Table className="w-full text-left text-sm">
                                <TableHeader className="border-b">
                                    <TableRow>
                                        {data.columns?.map((column) => (
                                            <TableHead
                                                scope="col"
                                                key={column.key}
                                                className="p-2 font-medium"
                                            >
                                                {column.label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {data.rows.map((row, index) => (
                                        <TableRow key={index}>
                                            {(
                                                data.columns ??
                                                Object.keys(row)
                                                    .filter(
                                                        (key) =>
                                                            ![
                                                                "url",
                                                                "target",
                                                                "link_key",
                                                            ].includes(key),
                                                    )
                                                    .map((key) => ({
                                                        key,
                                                        label: key,
                                                    }))
                                            ).map((column) => (
                                                <TableCell
                                                    key={column.key}
                                                    className={`p-2 break-words ${["index", "pageViews", "sessions", "count"].includes(column.key) ? "text-right tabular-nums" : ""}`}
                                                >
                                                    {column.key ===
                                                    (row.link_key ??
                                                        data.link_key) ? (
                                                        <WidgetLink
                                                            url={String(
                                                                row.url ?? "",
                                                            )}
                                                            target={String(
                                                                row.target ??
                                                                    data.target ??
                                                                    "",
                                                            )}
                                                        >
                                                            {row[column.key]}
                                                        </WidgetLink>
                                                    ) : [
                                                          "index",
                                                          "pageViews",
                                                          "sessions",
                                                          "count",
                                                      ].includes(column.key) ? (
                                                        formatMetric(
                                                            row[column.key],
                                                        )
                                                    ) : (
                                                        row[column.key]
                                                    )}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        {!data.records?.length &&
                            !data.stats &&
                            !data.rows?.length && (
                                <div className="py-8 text-center">
                                    <p className="text-sm font-medium">
                                        {data.empty?.title ??
                                            t("No results found")}
                                    </p>
                                    {data.empty?.description && (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {data.empty.description}
                                        </p>
                                    )}
                                </div>
                            )}
                        {data.pagination && data.pagination.last_page > 1 && (
                            <nav
                                aria-label={`${widget.title}: ${t("Pagination")}`}
                                className="flex flex-wrap items-center justify-between gap-3 border-t pt-3"
                            >
                                <span className="text-xs text-muted-foreground">
                                    {data.pagination.current_page} /{" "}
                                    {data.pagination.last_page} ·{" "}
                                    {data.pagination.total.toLocaleString()}{" "}
                                    {t("results")}
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            data.pagination.current_page <= 1
                                        }
                                        onClick={() =>
                                            setPage(
                                                data.pagination!.current_page -
                                                    1,
                                            )
                                        }
                                    >
                                        {t("Previous")}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            data.pagination.current_page >=
                                            data.pagination.last_page
                                        }
                                        onClick={() =>
                                            setPage(
                                                data.pagination!.current_page +
                                                    1,
                                            )
                                        }
                                    >
                                        {t("Next")}
                                    </Button>
                                </div>
                            </nav>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
