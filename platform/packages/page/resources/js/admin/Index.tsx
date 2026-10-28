import { DataTableToolbar } from "@admin/components/data-table-toolbar";
import type {
    RegisteredField,
    RegisteredFilter,
} from "@admin/components/registered-table-controls";
import { RegisteredFilterEditor } from "@admin/components/registered-table-controls";
import { SearchInput } from "@admin/components/search-input";
import { useTableNavigation } from "@admin/hooks/use-table-navigation";
import { TablePagination } from "@admin/components/table-pagination";
import type { TableVisibility } from "@admin/components/table-tools";
import { Badge } from "@admin/components/ui/badge";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Dialog } from "@admin/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from "@admin/components/ui/dropdown-menu";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@admin/components/ui/table";
import AdminLayout from "@admin/layouts/admin-layout";
import { formatAdminDate } from "@admin/lib/date";
import { tableActionUrl } from "@admin/lib/table-query";
import { useAdminTranslation } from "@admin/lib/translate";
import { adminQueryOptions } from "@admin/lib/utils";
import type { Option } from "@admin/types";
import { Head, Link, router, usePage } from "@inertiajs/react";
import type { ColumnDef, RowSelectionState } from "@tanstack/react-table";
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from "@tanstack/react-table";
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    FileText,
    Pencil,
    Plus,
    Trash2,
    X,
} from "lucide-react";
import type { FormEvent } from "react";
import { useCallback, useEffect, useMemo, useState } from "react";
type PageRow = {
    id: string;
    name: string;
    status: string;
    status_label?: string;
    template?: string;
    template_label?: string;
    badges?: string[];
    created_at: string | null;
    edit_url: string;
    delete_url: string;
};
const columnWidths: Record<string, string> = {
    select: "w-14",
    id: "w-16",
    status: "w-36",
    created_at: "w-44",
    actions: "w-20 text-right",
};
type Filters = {
    search: string;
    status: string;
    template?: string;
    sort: string;
    direction: string;
    per_page?: number;
    advanced?: RegisteredFilter[];
};
type Props = {
    pages: {
        data: PageRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Filters;
    bulkFields?: string[];
    filterFields?: RegisteredField[];
    statuses: Option[];
    templates?: Option[];
    tableButtons?: string[];
    tableVisibility?: TableVisibility;
    permissions: { create: boolean; edit: boolean; delete: boolean };
    urls: { index: string; create: string; bulk: string };
};
export default function Index({
    pages,
    filters,
    bulkFields = ["status", "template", "name", "created_at"],
    filterFields = [],
    statuses,
    templates = [],
    tableButtons = [],
    tableVisibility,
    permissions,
    urls,
}: Props) {
    const t = useAdminTranslation();
    const { admin, errors } = usePage<{
        admin: { locale?: string };
        errors: Record<string, string>;
        [key: string]: unknown;
    }>().props;
    const locale = admin.locale;
    const [visible, setVisible] = useState(tableVisibility?.columns ?? {});
    useEffect(
        () => setVisible(tableVisibility?.columns ?? {}),
        [tableVisibility],
    );
    const [advanced, setAdvanced] = useState<RegisteredFilter[]>(
        filters.advanced ?? [],
    );
    const [search, setSearch] = useState(filters.search ?? "");
    const [selected, setSelected] = useState<RowSelectionState>({});
    const [showFilters, setShowFilters] = useState(
        Boolean(filters.advanced?.length),
    );

    const [deleteTarget, setDeleteTarget] = useState<PageRow | "bulk" | null>(
        null,
    );
    const selectionContext = JSON.stringify(filters);
    useEffect(() => {
        setSelected({});
        setDeleteTarget(null);
        setSearch(filters.search ?? "");
        setAdvanced(filters.advanced ?? []);
    }, [pages.data, selectionContext, filters.search, filters.advanced]);
    const [busy, setBusy] = useState(false);
    const { loading: filtering, get: tableGet } = useTableNavigation();
    const [bulkValue, setBulkValue] = useState("");
    const [bulkField, setBulkField] = useState(bulkFields[0] ?? "name");
    const visit = useCallback(
        (changes: Partial<Filters>) => {
            tableGet(
                urls.index,
                { ...filters, ...changes, page: 1 },
                {
                    ...adminQueryOptions,
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => setSelected({}),
                },
            );
        },
        [urls.index, filters, tableGet],
    );
    const hasFilters = !!(
        filters.search ||
        filters.status ||
        filters.template ||
        filters.advanced?.length
    );
    function resetFilters() {
        setSearch("");
        setAdvanced([]);
        visit({ search: "", status: "", template: "", advanced: [] });
    }
    const columns = useMemo<ColumnDef<PageRow>[]>(() => {
        function SortIcon({ field }: { field: string }) {
            const Icon =
                filters.sort !== field
                    ? ArrowUpDown
                    : filters.direction === "asc"
                      ? ArrowUp
                      : ArrowDown;
            return <Icon aria-hidden="true" className="size-3.5" />;
        }
        function sort(field: string) {
            visit({
                sort: field,
                direction:
                    filters.sort === field && filters.direction === "asc"
                        ? "desc"
                        : "asc",
            });
        }
        function SortHeader({
            field,
            label,
        }: {
            field: string;
            label: string;
        }) {
            return (
                <button
                    type="button"
                    className="flex min-h-7 items-center gap-2 rounded px-1 outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    onClick={() => sort(field)}
                >
                    {t(label)}
                    <SortIcon field={field} />
                </button>
            );
        }
        return [
            ...(permissions.delete || permissions.edit
                ? [
                      {
                          id: "select",
                          header: ({
                              table,
                          }: {
                              table: ReturnType<typeof useReactTable<PageRow>>;
                          }) => (
                              <Checkbox
                                  aria-label={t(
                                      "Select all pages on this page",
                                  )}
                                  checked={table.getIsAllRowsSelected()}
                                  indeterminate={table.getIsSomeRowsSelected()}
                                  onCheckedChange={(checked) =>
                                      table.toggleAllRowsSelected(
                                          checked === true,
                                      )
                                  }
                              />
                          ),
                          cell: ({
                              row,
                          }: {
                              row: {
                                  getIsSelected: () => boolean;
                                  toggleSelected: (selected: boolean) => void;
                                  original: PageRow;
                              };
                          }) => (
                              <Checkbox
                                  aria-label={t("Select :name", {
                                      name: row.original.name,
                                  })}
                                  checked={row.getIsSelected()}
                                  onCheckedChange={(checked) =>
                                      row.toggleSelected(checked === true)
                                  }
                              />
                          ),
                      },
                  ]
                : []),
            {
                accessorKey: "id",
                header: () => <SortHeader field="id" label="ID" />,
                cell: ({ row }) => (
                    <span className="font-mono text-xs tabular-nums text-muted-foreground">
                        {row.original.id}
                    </span>
                ),
            },
            {
                accessorKey: "name",
                header: () => (
                    <button
                        type="button"
                        className="flex min-h-7 items-center gap-2 rounded px-1 outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        onClick={() => sort("name")}
                    >
                        {t("Name")}
                        <SortIcon field="name" />
                    </button>
                ),
                cell: ({ row }) => (
                    <div className="flex min-w-40 flex-wrap items-center gap-2">
                        {permissions.edit ? (
                            <Link
                                href={row.original.edit_url}
                                className="font-medium text-foreground hover:text-primary hover:underline"
                            >
                                {row.original.name}
                            </Link>
                        ) : (
                            <span className="font-medium">
                                {row.original.name}
                            </span>
                        )}
                        {row.original.badges?.map((badge) => (
                            <Badge variant="outline" key={badge}>
                                {badge}
                            </Badge>
                        ))}
                    </div>
                ),
            },
            {
                accessorKey: "template",
                header: () => <SortHeader field="template" label="Template" />,
                cell: ({ row }) =>
                    row.original.template_label ?? row.original.template ?? "—",
            },
            {
                accessorKey: "status",
                header: () => <SortHeader field="status" label="Status" />,
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.status === "published"
                                ? "success"
                                : row.original.status === "pending"
                                  ? "warning"
                                  : "default"
                        }
                    >
                        <span
                            aria-hidden="true"
                            className="size-1.5 rounded-full bg-current opacity-70"
                        />
                        {row.original.status_label ?? row.original.status}
                    </Badge>
                ),
            },
            {
                accessorKey: "created_at",
                header: () => (
                    <button
                        type="button"
                        className="flex min-h-7 items-center gap-2 rounded px-1 outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        onClick={() => sort("created_at")}
                    >
                        {t("Created")}
                        <SortIcon field="created_at" />
                    </button>
                ),
                cell: ({ row }) => {
                    const raw = row.original.created_at;
                    if (!raw) {
                        return <span className="text-muted-foreground">—</span>;
                    }

                    return (
                        <time
                            dateTime={raw}
                            className="whitespace-nowrap tabular-nums text-muted-foreground"
                        >
                            {formatAdminDate(raw, locale)}
                        </time>
                    );
                },
            },
            {
                id: "actions",
                header: t("Actions"),
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        {(permissions.edit || permissions.delete) && (
                            <DropdownMenu
                                label={t("Actions for :name", {
                                    name: row.original.name,
                                })}
                            >
                                {permissions.edit && (
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={row.original.edit_url}
                                            aria-label={t("Edit :name", {
                                                name: row.original.name,
                                            })}
                                        >
                                            <Pencil size={16} />
                                            {t("Edit page")}
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {permissions.delete && (
                                    <>
                                        {permissions.edit && (
                                            <DropdownMenuSeparator />
                                        )}
                                        <DropdownMenuItem
                                            destructive
                                            onSelect={() =>
                                                setDeleteTarget(row.original)
                                            }
                                            aria-label={t("Delete :name", {
                                                name: row.original.name,
                                            })}
                                        >
                                            <Trash2
                                                size={16}
                                                className="text-destructive"
                                            />
                                            {t("Delete")}
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenu>
                        )}
                    </div>
                ),
            },
        ];
    }, [permissions, filters, t, locale, visit]);
    const table = useReactTable({
        data: pages.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getRowId: (row) => String(row.id),
        state: {
            rowSelection: selected,
            columnVisibility: Object.fromEntries(
                Object.entries(visible).map(([key, value]) => [
                    key,
                    value !== 0,
                ]),
            ),
        },
        onRowSelectionChange: setSelected,
        enableRowSelection: permissions.delete || permissions.edit,
        manualPagination: true,
    });
    const ids = Object.keys(selected).filter((id) => selected[id]);
    function remove() {
        if (!deleteTarget) {
            return;
        }
        setBusy(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setSelected({});
            },
            onFinish: () => setBusy(false),
        };
        if (deleteTarget === "bulk") {
            router.post(
                tableActionUrl(urls.bulk, {
                    ...filters,
                    page: pages.current_page,
                }),
                { action: "delete", ids },
                options,
            );
        } else {
            router.delete(deleteTarget.delete_url, options);
        }
    }
    function updateStatus() {
        if (!bulkValue || !ids.length) {
            return;
        }
        setBusy(true);
        router.post(
            tableActionUrl(urls.bulk, { ...filters, page: pages.current_page }),
            { action: "update", ids, field: bulkField, value: bulkValue },
            {
                preserveScroll: true,
                onSuccess: () => setSelected({}),
                onFinish: () => setBusy(false),
            },
        );
    }
    return (
        <AdminLayout
            title={t("Pages")}
            actions={
                permissions.create && (
                    <Button asChild>
                        <Link href={urls.create}>
                            <Plus size={16} />
                            {t("Create page")}
                        </Link>
                    </Button>
                )
            }
        >
            <Head title={t("Pages")} />
            <Card className="overflow-hidden" aria-busy={filtering}>
                <DataTableToolbar
                    loading={filtering}
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        visit({ search });
                    }}
                    tools={{
                        buttons: tableButtons,
                        columns: [
                            { key: "id", label: "ID" },
                            { key: "name", label: "Name" },
                            { key: "template", label: "Template" },
                            { key: "created_at", label: "Created" },
                            { key: "status", label: "Status" },
                        ],
                        visibility: tableVisibility
                            ? { ...tableVisibility, columns: visible }
                            : undefined,
                        onVisibilityChange: setVisible,
                        indexUrl: urls.index,
                        filters,
                    }}
                >
                    <SearchInput
                        className="w-full min-w-48 sm:w-auto sm:flex-1"
                        aria-label={t("Search pages")}
                        placeholder={t("Search pages")}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onClear={() => setSearch("")}
                        onKeyDown={(event) => {
                            if (event.key === "Escape") setSearch("");
                        }}
                    />
                    <Select
                        containerClassName="w-full sm:w-44"
                        disabled={filtering}
                        aria-label={t("Filter by status")}
                        value={filters.status ?? ""}
                        onValueChange={(value) => visit({ status: value })}
                    >
                        <option value="">{t("All statuses")}</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </Select>
                    <Select
                        containerClassName="w-full sm:w-44"
                        disabled={filtering}
                        aria-label={t("Filter by template")}
                        value={filters.template ?? ""}
                        onValueChange={(value) => visit({ template: value })}
                    >
                        <option value="">{t("All templates")}</option>
                        {templates.map((template) => (
                            <option key={template.value} value={template.value}>
                                {template.label}
                            </option>
                        ))}
                    </Select>
                    <Button
                        variant="outline"
                        type="submit"
                        disabled={filtering}
                    >
                        {t("Search")}
                    </Button>
                    {filterFields.length > 0 && (
                        <Button
                            variant="outline"
                            aria-expanded={showFilters}
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            {t("Advanced filters")}
                        </Button>
                    )}
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            onClick={resetFilters}
                            disabled={filtering}
                        >
                            <X aria-hidden="true" />
                            {t("Clear filters")}
                        </Button>
                    )}
                </DataTableToolbar>
                {filterFields.length > 0 && showFilters && (
                    <fieldset className="contents" disabled={filtering}>
                        <RegisteredFilterEditor
                            fields={filterFields}
                            value={advanced}
                            onChange={setAdvanced}
                            onApply={() => visit({ advanced })}
                        />
                    </fieldset>
                )}
                {Object.entries(errors)
                    .filter(([key]) => key.startsWith("advanced"))
                    .map(([key, message]) => (
                        <p
                            key={key}
                            role="alert"
                            className="px-4 py-2 text-sm text-destructive"
                        >
                            {message}
                        </p>
                    ))}
                {ids.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 border-b bg-muted/50 px-4 py-3">
                        <span className="text-sm font-medium">
                            {t(":count selected", { count: ids.length })}
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setSelected({})}
                            aria-label={t("Clear selection")}
                        >
                            <X aria-hidden="true" />
                        </Button>
                        {permissions.edit && (
                            <>
                                <Select
                                    containerClassName="w-full sm:w-auto sm:min-w-40"
                                    className="sm:h-9"
                                    aria-label={t("Bulk field")}
                                    value={bulkField}
                                    onValueChange={(value) => {
                                        setBulkField(value);
                                        setBulkValue("");
                                    }}
                                >
                                    {bulkFields.map((field) => (
                                        <option key={field} value={field}>
                                            {t(
                                                (
                                                    {
                                                        status: "Status",
                                                        template: "Template",
                                                        name: "Name",
                                                        created_at:
                                                            "Created date",
                                                    } as Record<string, string>
                                                )[field] ?? field,
                                            )}
                                        </option>
                                    ))}
                                </Select>
                                {["status", "template"].includes(bulkField) ? (
                                    <Select
                                        containerClassName="w-full sm:w-auto sm:min-w-40"
                                        className="sm:h-9"
                                        aria-label={t("Bulk value")}
                                        value={bulkValue}
                                        onValueChange={(value) =>
                                            setBulkValue(value)
                                        }
                                    >
                                        <option value="">
                                            {t("Choose value…")}
                                        </option>
                                        {(bulkField === "status"
                                            ? statuses
                                            : templates
                                        ).map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                ) : (
                                    <Input
                                        className="h-8 w-48"
                                        aria-label={t("Bulk value")}
                                        type={
                                            bulkField === "created_at"
                                                ? "datetime-local"
                                                : "text"
                                        }
                                        value={bulkValue}
                                        onChange={(event) =>
                                            setBulkValue(event.target.value)
                                        }
                                    />
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={updateStatus}
                                    disabled={!bulkValue || busy}
                                    loading={busy}
                                >
                                    {t("Apply")}
                                </Button>
                            </>
                        )}
                        {permissions.delete && (
                            <Button
                                variant="destructive"
                                size="sm"
                                disabled={busy}
                                onClick={() => setDeleteTarget("bulk")}
                            >
                                {t("Delete selected")}
                            </Button>
                        )}
                    </div>
                )}
                <div className="overflow-x-auto">
                    <Table className="w-full text-left text-sm">
                        <caption className="sr-only">{t("Pages")}</caption>
                        <TableHeader className="border-b bg-muted/30 text-xs text-muted-foreground">
                            {table.getHeaderGroups().map((group) => (
                                <TableRow key={group.id}>
                                    {group.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            scope="col"
                                            aria-sort={
                                                [
                                                    "id",
                                                    "name",
                                                    "template",
                                                    "status",
                                                    "created_at",
                                                ].includes(header.id)
                                                    ? filters.sort === header.id
                                                        ? filters.direction ===
                                                          "asc"
                                                            ? "ascending"
                                                            : "descending"
                                                        : "none"
                                                    : undefined
                                            }
                                            className={`px-4 py-2 font-medium ${columnWidths[header.id] ?? ""}`}
                                        >
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={
                                        row.getIsSelected()
                                            ? "selected"
                                            : undefined
                                    }
                                    className="border-b transition-colors last:border-b-0 hover:bg-muted/40 data-[state=selected]:bg-primary/5"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className="px-4 py-2"
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                            {!pages.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={
                                            table.getVisibleLeafColumns().length
                                        }
                                        className="px-4 py-16 text-center text-muted-foreground"
                                    >
                                        <div className="mx-auto flex max-w-sm flex-col items-center gap-3">
                                            <span className="flex size-9 items-center justify-center">
                                                <FileText
                                                    aria-hidden="true"
                                                    className="size-6"
                                                />
                                            </span>
                                            <p className="font-medium text-foreground">
                                                {t("No pages found.")}
                                            </p>
                                            <p className="text-sm">
                                                {hasFilters
                                                    ? t(
                                                          "Try another search or clear your filters.",
                                                      )
                                                    : t(
                                                          "Create your first page to start publishing content.",
                                                      )}
                                            </p>
                                            {hasFilters ? (
                                                <Button
                                                    variant="outline"
                                                    onClick={resetFilters}
                                                >
                                                    {t("Clear filters")}
                                                </Button>
                                            ) : (
                                                permissions.create && (
                                                    <Button asChild>
                                                        <Link
                                                            href={urls.create}
                                                        >
                                                            <Plus aria-hidden="true" />
                                                            {t("Create page")}
                                                        </Link>
                                                    </Button>
                                                )
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <TablePagination
                    loading={filtering}
                    total={pages.total}
                    previous={pages.links[0]?.url}
                    next={pages.links.at(-1)?.url}
                    currentPage={pages.current_page}
                    lastPage={pages.last_page}
                    perPage={pages.per_page}
                    onPageSize={(size) => visit({ per_page: size })}
                    onVisit={(url) =>
                        tableGet(
                            url,
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                />
            </Card>
            <Dialog
                open={!!deleteTarget}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setDeleteTarget(null);
                    }
                }}
                title={t("Delete pages?")}
                size="sm"
                description={
                    deleteTarget === "bulk"
                        ? `${t("Delete :count selected pages?", { count: ids.length })} ${t("This action cannot be undone.")}`
                        : `${t("Delete “:name”?", { name: deleteTarget?.name ?? "" })} ${t("This action cannot be undone.")}`
                }
            >
                <div className="flex justify-end gap-3">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setDeleteTarget(null)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={busy}
                        loading={busy}
                        onClick={remove}
                    >
                        {t("Delete")}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
