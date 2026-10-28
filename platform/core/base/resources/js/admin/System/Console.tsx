import { DataTableToolbar } from "@admin/components/data-table-toolbar";
import { TablePagination } from "@admin/components/table-pagination";
import { useTableNavigation } from "@admin/hooks/use-table-navigation";
import type { TableVisibility } from "@admin/components/table-tools";
import { SearchInput } from "@admin/components/search-input";
import { FormSection } from "@admin/components/form-section";
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import { safeConsoleLink } from "@admin/lib/console-link";
import { useAdminDate } from "@admin/lib/date";
import { notify } from "@admin/components/toast";
import { Head, router } from "@inertiajs/react";
import { AlertTriangle } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import axios from "axios";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { Checkbox } from "@admin/components/ui/checkbox";
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
} from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
import { Badge } from "@admin/components/ui/badge";
import { Field } from "@admin/components/field";
import { useAdminTranslation } from "@admin/lib/translate";
type Action = {
    label: string;
    url: string;
    method?: "post" | "put" | "delete";
    data?: Record<string, string | number | string[]>;
    confirm?: string;
    destructive?: boolean;
    download?: boolean;
    disabled?: boolean;
    selection?: boolean;
    fields?: { name: string; label: string; type?: string; value?: string }[];
};
type Row = {
    id: string | number;
    values: Record<string, string | number | null>;
    actions?: Action[];
    unread?: boolean;
    links?: Record<string, string>;
};
export type ConsoleProps = {
    tableButtons?: string[];
    tableVisibility?: TableVisibility;
    tableUrl?: string;
    tableFilters?: Record<string, unknown>;
    title: string;
    description?: string;
    warnings?: string[];
    actions?: Action[];
    columns?: { key: string; label: string; sortable?: boolean }[];
    rows?: Row[];
    stats?: { label: string; value: string | number; url?: string }[];
    sections?: {
        title: string;
        items: { title: string; description: string; url: string }[];
    }[];
    details?: Record<string, string | number | null>;
    pagination?: { prev: string | null; next: string | null; total: number };
    selectable?: {
        name: string;
        values: string[];
        disabled?: string[];
        action: Action;
    };
    secret?: string | null;
    search?: { url: string; value: string };
    bulkDelete?: { url: string } | null;
};
export default function Console({
    title,
    description,
    warnings = [],
    actions = [],
    rows = [],
    columns = [],
    stats = [],
    details,
    sections = [],
    pagination,
    selectable,
    secret,
    search,
    bulkDelete,
    tableButtons = [],
    tableVisibility,
    tableUrl,
    tableFilters = {},
}: ConsoleProps) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    const { loading: filtering, get: tableGet } = useTableNavigation();
    const [visible, setVisible] = useState(tableVisibility?.columns ?? {});
    useEffect(
        () => setVisible(tableVisibility?.columns ?? {}),
        [tableVisibility],
    );
    const visibleColumns = columns.filter(
        (column) => visible[column.key] !== 0,
    );

    function renderValue(key: string, value: Row["values"][string]) {
        return key.endsWith("_at") && typeof value === "string" ? (
            <time dateTime={value}>{formatDate(value, true)}</time>
        ) : (
            String(value ?? "—")
        );
    }
    const [pending, setPending] = useState<Action | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const [selected, setSelected] = useState<string[]>([]);
    const [selectedRows, setSelectedRows] = useState<string[]>([]);
    const rowSelectionKey = JSON.stringify(rows.map((row) => row.id));
    useEffect(() => setSelectedRows([]), [rowSelectionKey, search?.value]);
    const [busy, setBusy] = useState(false);
    const [runningAction, setRunningAction] = useState<Action | null>(null);
    const activeAction = useRef<AbortController | null>(null);
    useEffect(
        () => () => {
            const controller = activeAction.current;
            activeAction.current = null;
            controller?.abort();
        },
        [],
    );
    const [message, setMessage] = useState("");
    const [query, setQuery] = useState(search?.value ?? "");
    const [revealed, setRevealed] = useState(Boolean(secret));
    useEffect(() => setQuery(search?.value ?? ""), [search?.value]);
    async function perform(action: Action) {
        if (activeAction.current) return;
        const controller = new AbortController();
        activeAction.current = controller;
        setRunningAction(action);
        setBusy(true);
        setMessage("");
        try {
            const response = await axios.request({
                url: action.url,
                method: action.method ?? "post",
                signal: controller.signal,
                data: {
                    ...action.data,
                    ...values,
                    ...(selectable ? { [selectable.name]: selected } : {}),
                    ...(action.selection ? { ids: selectedRows } : {}),
                },
            });
            if (activeAction.current !== controller) return;
            if (response.data.error) throw new Error(response.data.message);
            notify(response.data.message ?? t("Saved"));
            setSelectedRows([]);
            await new Promise<void>((resolve) => {
                let started = false;
                router.reload({
                    onStart: () => {
                        started = true;
                    },
                    onCancelToken: (token) => {
                        controller.signal.addEventListener(
                            "abort",
                            () => token.cancel(),
                            { once: true },
                        );
                    },
                    onFinish: () => resolve(),
                });
                if (!started) resolve();
            });
            if (activeAction.current === controller) setPending(null);
        } catch (error) {
            if (activeAction.current !== controller) return;
            notify(
                axios.isAxiosError(error)
                    ? error.response?.data?.message || error.message
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
                true,
            );
            setMessage(
                axios.isAxiosError(error)
                    ? (error.response?.data?.message ?? error.message)
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
            );
        } finally {
            if (activeAction.current === controller) {
                activeAction.current = null;
                setRunningAction(null);
                setBusy(false);
            }
        }
    }
    function actionButton(action: Action, index: number) {
        return action.method ? (
            <Button
                key={index}
                variant={action.destructive ? "destructive" : "outline"}
                disabled={action.disabled || busy}
                loading={busy && runningAction === action && !pending}
                onClick={() => {
                    if (action.confirm || action.fields?.length) {
                        setValues(
                            Object.fromEntries(
                                action.fields?.map((field) => [
                                    field.name,
                                    field.value ?? "",
                                ]) ?? [],
                            ),
                        );
                        setPending(action);
                    } else void perform(action);
                }}
            >
                {action.label}
            </Button>
        ) : (
            <Button key={index} variant="outline" asChild>
                <a href={action.url} download={action.download || undefined}>
                    {action.label}
                </a>
            </Button>
        );
    }
    return (
        <AdminLayout
            title={title}
            actions={
                <div className="flex flex-wrap gap-2">
                    {actions.map(actionButton)}
                </div>
            }
        >
            <Head title={title} />
            <div className="space-y-5">
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
                {warnings.length > 0 && (
                    <div className="flex gap-3 rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0 text-amber-600"
                            aria-hidden="true"
                        />
                        <div className="space-y-2">
                            {warnings.map((warning) => (
                                <p key={warning}>{warning}</p>
                            ))}
                        </div>
                    </div>
                )}
                {secret && revealed && (
                    <Card>
                        <CardContent>
                            <p className="mb-2 text-sm font-semibold">
                                {t(
                                    "Copy this token now. It will only be shown once.",
                                )}
                            </p>
                            <code className="block break-all rounded-md bg-muted p-3">
                                {secret}
                            </code>
                            <Button
                                className="mt-3"
                                variant="outline"
                                onClick={() => setRevealed(false)}
                            >
                                {t("Dismiss")}
                            </Button>
                        </CardContent>
                    </Card>
                )}
                {message && (
                    <p role="status" className="rounded-md border p-3 text-sm">
                        {message}
                    </p>
                )}
                {stats.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map((stat) => (
                            <Card key={stat.label}>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        {stat.label}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold tabular-nums">
                                        {stat.url ? (
                                            <a href={stat.url}>{stat.value}</a>
                                        ) : (
                                            stat.value
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                {details && (
                    <Card>
                        <CardContent>
                            <dl className="divide-y">
                                {Object.entries(details).map(([key, value]) => (
                                    <div
                                        key={key}
                                        className="grid gap-2 py-3 text-sm sm:grid-cols-3"
                                    >
                                        <dt className="font-medium">{key}</dt>
                                        <dd className="break-all text-muted-foreground sm:col-span-2">
                                            {String(value ?? "—")}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                )}
                {selectable && (
                    <Card>
                        <CardContent className="space-y-2">
                            {selectable.values.map((value) => (
                                <label
                                    key={value}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={selected.includes(value)}
                                        disabled={selectable.disabled?.includes(
                                            value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            setSelected(
                                                checked === true
                                                    ? [...selected, value]
                                                    : selected.filter(
                                                          (item) =>
                                                              item !== value,
                                                      ),
                                            )
                                        }
                                    />
                                    {value}
                                </label>
                            ))}
                            <div className="pt-3">
                                {actionButton(selectable.action, 0)}
                            </div>
                        </CardContent>
                    </Card>
                )}
                {columns.length > 0 && (
                    <Card aria-busy={filtering}>
                        {(search || tableUrl) && (
                            <DataTableToolbar
                                loading={filtering}
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (!search) return;
                                    tableGet(search.url, {
                                        ...tableFilters,
                                        search: query,
                                        page: 1,
                                    });
                                }}
                                tools={
                                    tableUrl
                                        ? {
                                              buttons: tableButtons,
                                              columns,
                                              visibility: tableVisibility
                                                  ? {
                                                        ...tableVisibility,
                                                        columns: visible,
                                                    }
                                                  : undefined,
                                              onVisibilityChange: setVisible,
                                              indexUrl: tableUrl,
                                              filters: tableFilters,
                                          }
                                        : undefined
                                }
                            >
                                {search && (
                                    <>
                                        <SearchInput
                                            className="min-w-48 flex-1"
                                            onClear={() => setQuery("")}
                                            aria-label={t("Search")}
                                            value={query}
                                            onChange={(event) =>
                                                setQuery(event.target.value)
                                            }
                                        />
                                        <Button type="submit" variant="outline">
                                            {t("Search")}
                                        </Button>
                                    </>
                                )}
                            </DataTableToolbar>
                        )}
                        {bulkDelete && selectedRows.length > 0 && (
                            <div className="flex flex-wrap items-center gap-3 border-b p-4">
                                <span role="status" className="text-sm">
                                    {t(":count selected", {
                                        count: selectedRows.length,
                                    })}
                                </span>
                                {actionButton(
                                    {
                                        label: t("Delete selected"),
                                        url: bulkDelete.url,
                                        method: "delete",
                                        selection: true,
                                        destructive: true,
                                        confirm: t(
                                            "Delete the selected logs permanently?",
                                        ),
                                    },
                                    0,
                                )}
                                <Button
                                    variant="ghost"
                                    disabled={busy}
                                    onClick={() => setSelectedRows([])}
                                >
                                    {t("Clear selection")}
                                </Button>
                            </div>
                        )}
                        <div className="overflow-x-auto">
                            <Table className="w-full text-left text-sm">
                                <TableHeader className="border-b bg-muted/40">
                                    <TableRow>
                                        {bulkDelete && (
                                            <TableHead className="w-12 px-4 py-3">
                                                <Checkbox
                                                    aria-label={t(
                                                        "Select all rows on this page",
                                                    )}
                                                    disabled={
                                                        busy || !rows.length
                                                    }
                                                    checked={
                                                        selectedRows.length >
                                                            0 &&
                                                        selectedRows.length <
                                                            rows.length
                                                            ? "indeterminate"
                                                            : rows.length > 0 &&
                                                              selectedRows.length ===
                                                                  rows.length
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedRows(
                                                            checked === true
                                                                ? rows.map(
                                                                      (row) =>
                                                                          String(
                                                                              row.id,
                                                                          ),
                                                                  )
                                                                : [],
                                                        )
                                                    }
                                                />
                                            </TableHead>
                                        )}
                                        {visibleColumns.map((column) => (
                                            <TableHead
                                                key={column.key}
                                                className="px-4 py-3 font-medium"
                                                aria-sort={
                                                    column.sortable
                                                        ? tableFilters.sort ===
                                                          column.key
                                                            ? tableFilters.direction ===
                                                              "asc"
                                                                ? "ascending"
                                                                : "descending"
                                                            : "none"
                                                        : undefined
                                                }
                                            >
                                                {column.sortable && tableUrl ? (
                                                    <button
                                                        type="button"
                                                        className="flex items-center gap-1 whitespace-nowrap hover:text-foreground"
                                                        onClick={() =>
                                                            tableGet(
                                                                tableUrl,
                                                                {
                                                                    ...tableFilters,
                                                                    sort: column.key,
                                                                    direction:
                                                                        tableFilters.sort ===
                                                                            column.key &&
                                                                        tableFilters.direction ===
                                                                            "asc"
                                                                            ? "desc"
                                                                            : "asc",
                                                                    page: 1,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {t(column.label)}{" "}
                                                        {tableFilters.sort ===
                                                        column.key
                                                            ? tableFilters.direction ===
                                                              "asc"
                                                                ? "↑"
                                                                : "↓"
                                                            : ""}
                                                    </button>
                                                ) : (
                                                    column.label
                                                )}
                                            </TableHead>
                                        ))}
                                        <TableHead className="px-4 py-3">
                                            {t("Actions")}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {rows.map((row) => (
                                        <TableRow
                                            key={row.id}
                                            className={
                                                row.unread
                                                    ? "bg-primary/5 font-medium"
                                                    : undefined
                                            }
                                        >
                                            {bulkDelete && (
                                                <TableCell className="px-4 py-3">
                                                    <Checkbox
                                                        aria-label={t(
                                                            "Select row :id",
                                                            { id: row.id },
                                                        )}
                                                        disabled={busy}
                                                        checked={selectedRows.includes(
                                                            String(row.id),
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setSelectedRows(
                                                                (current) =>
                                                                    checked ===
                                                                    true
                                                                        ? [
                                                                              ...current,
                                                                              String(
                                                                                  row.id,
                                                                              ),
                                                                          ]
                                                                        : current.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  String(
                                                                                      row.id,
                                                                                  ),
                                                                          ),
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                            )}
                                            {visibleColumns.map((column) => (
                                                <TableCell
                                                    key={column.key}
                                                    className="max-w-sm break-words px-4 py-3"
                                                >
                                                    {safeConsoleLink(
                                                        row.links?.[column.key],
                                                    ) ? (
                                                        <a
                                                            href={safeConsoleLink(
                                                                row.links?.[
                                                                    column.key
                                                                ],
                                                            )!}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="break-all text-primary-ink underline underline-offset-2"
                                                        >
                                                            {renderValue(
                                                                column.key,
                                                                row.values[
                                                                    column.key
                                                                ],
                                                            )}
                                                        </a>
                                                    ) : column.key ===
                                                          "status" &&
                                                      row.unread !==
                                                          undefined ? (
                                                        <Badge
                                                            variant={
                                                                row.unread
                                                                    ? "default"
                                                                    : "outline"
                                                            }
                                                        >
                                                            {
                                                                row.values[
                                                                    column.key
                                                                ]
                                                            }
                                                        </Badge>
                                                    ) : (
                                                        renderValue(
                                                            column.key,
                                                            row.values[
                                                                column.key
                                                            ],
                                                        )
                                                    )}
                                                </TableCell>
                                            ))}
                                            <TableCell className="px-4 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    {row.actions?.map(
                                                        actionButton,
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {!rows.length && (
                                        <TableRow>
                                            <TableCell
                                                className="p-8 text-center text-muted-foreground"
                                                colSpan={
                                                    columns.length +
                                                    1 +
                                                    (bulkDelete ? 1 : 0)
                                                }
                                            >
                                                {t("No results found")}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        {pagination && (
                            <TablePagination
                                loading={filtering}
                                total={pagination.total}
                                previous={pagination.prev}
                                next={pagination.next}
                                onVisit={(url) =>
                                    tableGet(
                                        url,
                                        {},
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            />
                        )}
                    </Card>
                )}
                {sections.map((section) => (
                    <FormSection
                        key={section.title}
                        title={section.title}
                        contentClassName="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        {section.items.map((item) => (
                            <a
                                key={item.url}
                                href={item.url}
                                className="rounded-md border p-4 hover:bg-muted"
                            >
                                <h3 className="text-sm font-semibold">
                                    {item.title}
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {item.description}
                                </p>
                            </a>
                        ))}
                    </FormSection>
                ))}
            </div>
            <Dialog
                open={Boolean(pending)}
                onOpenChange={(open) => {
                    if (!busy && !open) setPending(null);
                }}
                title={pending?.label ?? ""}
                description={pending?.confirm}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (pending) void perform(pending);
                    }}
                    className="space-y-4"
                >
                    {pending?.fields?.map((field) => (
                        <Field key={field.name} label={field.label}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type={field.type ?? "text"}
                                    value={values[field.name] ?? ""}
                                    onChange={(e) =>
                                        setValues({
                                            ...values,
                                            [field.name]: e.target.value,
                                        })
                                    }
                                    required={field.name === "name"}
                                />
                            )}
                        </Field>
                    ))}
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => setPending(null)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                pending?.destructive ? "destructive" : "default"
                            }
                            loading={busy}
                        >
                            {pending?.label}
                        </Button>
                    </div>
                </form>
            </Dialog>
        </AdminLayout>
    );
}
