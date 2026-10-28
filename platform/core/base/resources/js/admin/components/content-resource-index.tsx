import { useTableNavigation } from "@admin/hooks/use-table-navigation";
import { TablePagination } from "@admin/components/table-pagination";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminDate } from "@admin/lib/date";
import { tableActionUrl } from "@admin/lib/table-query";
import { useAdminTranslation } from "@admin/lib/translate";
import { adminQueryOptions } from "@admin/lib/utils";
import type { Option } from "@admin/types";
import { Head, Link, router } from "@inertiajs/react";
import { Copy, Files, Pencil, Plus, Trash2 } from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { DataTableToolbar } from "./data-table-toolbar";
import {
    RegisteredBulkChange,
    RegisteredFilterEditor,
    type RegisteredFilter as AdvancedFilter,
    type RegisteredField as BulkField,
} from "./registered-table-controls";
import { SearchInput } from "./search-input";
import type { TableVisibility } from "./table-tools";
import { notify } from "./toast";
import { Badge } from "./ui/badge";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Checkbox } from "./ui/checkbox";
import { Dialog } from "./ui/dialog";
import { DropdownMenu, DropdownMenuItem } from "./ui/dropdown-menu";
import { Select } from "./ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "./ui/table";

type Row = {
    id: string;
    name: string;
    alias?: string;
    shortcode?: string;
    type?: string;
    type_label?: string;
    email?: string;
    image?: string;
    phone?: string;
    order?: number;
    locations_display?: string;
    items_count?: number;
    categories?: { name: string; url: string | null }[];
    author?: string;
    author_url?: string;
    confirmed_at?: boolean;
    status: string;
    status_label: string;
    created_at?: string;
    edit_url: string;
    delete_url: string;
    export_url?: string;
};
export type ContentIndexProps = {
    tableButtons?: string[];
    tableVisibility?: TableVisibility;
    columns?: { key: string; label: string; sortable: boolean }[];
    extraActions?: ReactNode;
    tableToolsExtra?: ReactNode;
    bulkFields?: BulkField[];
    title: string;
    resource: string;
    items: {
        data: Row[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url?: string;
        next_page_url?: string;
    };
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
        ref_lang?: string;
        advanced?: AdvancedFilter[];
    };
    statuses: Option[];
    permissions: { create: boolean; edit: boolean; delete: boolean };
    urls: { index: string; create?: string | null; bulk?: string };
};
export function ContentResourceIndex({
    tableButtons = ["reload"],
    tableVisibility,
    extraActions,
    tableToolsExtra,
    columns = [
        { key: "id", label: "ID", sortable: true },
        { key: "name", label: "Name", sortable: true },
        { key: "status", label: "Status", sortable: true },
        { key: "created_at", label: "Created at", sortable: true },
    ],
    bulkFields = [],
    title,
    resource,
    items,
    filters,
    statuses,
    permissions,
    urls,
}: ContentIndexProps) {
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
    const [search, setSearch] = useState(filters.search ?? "");
    const [target, setTarget] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const [bulkDelete, setBulkDelete] = useState(false);
    const [bulkStatus, setBulkStatus] = useState(
        statuses[0]?.value ?? "published",
    );
    const [bulkField, setBulkField] = useState("");
    const [bulkValue, setBulkValue] = useState("");
    const [bulkError, setBulkError] = useState("");
    const [advanced, setAdvanced] = useState<AdvancedFilter[]>(
        filters.advanced ?? [],
    );
    const [showFilters, setShowFilters] = useState(
        Boolean(filters.advanced?.length),
    );
    const selectionContext = JSON.stringify(filters);
    useEffect(() => {
        setSelected([]);
        setBulkDelete(false);
        setBulkError("");
        setSearch(filters.search ?? "");
        setAdvanced(filters.advanced ?? []);
    }, [
        items.data,
        items.current_page,
        selectionContext,
        filters.search,
        filters.advanced,
    ]);
    const canSelect = Boolean(
        urls.bulk && (permissions.delete || permissions.edit),
    );
    function submitBulk(action: "delete" | "status" | "change") {
        if (!urls.bulk) return;
        setBusy(true);
        setBulkError("");
        router.post(
            tableActionUrl(urls.bulk, { ...filters, page: items.current_page }),
            {
                ids: selected,
                action,
                ...(action === "status"
                    ? { status: bulkStatus }
                    : action === "change"
                      ? { field: bulkField, value: bulkValue }
                      : {}),
            },
            {
                onSuccess: () => {
                    setSelected([]);
                    setBulkDelete(false);
                },
                onError: (errors) =>
                    setBulkError(Object.values(errors).join(" ")),
                onFinish: () => setBusy(false),
            },
        );
    }
    function visit(
        changes: Record<string, string | number | undefined | AdvancedFilter[]>,
    ) {
        setSelected([]);
        setBulkError("");
        tableGet(
            urls.index,
            { ...filters, ...changes, page: 1 },
            {
                ...adminQueryOptions,
                preserveState: true,
                preserveScroll: true,
                onError: (errors) =>
                    setBulkError(Object.values(errors).join(" ")),
            },
        );
    }
    return (
        <AdminLayout
            title={title}
            actions={
                <>
                    {extraActions}
                    {permissions.create && urls.create && (
                        <Button asChild>
                            <Link href={urls.create}>
                                <Plus />
                                {t("Create")}
                            </Link>
                        </Button>
                    )}
                </>
            }
        >
            <Head title={title} />
            <Card aria-busy={filtering}>
                {canSelect && selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 border-b bg-muted/40 p-4">
                        <span className="text-sm">
                            {selected.length} {t("selected")}
                        </span>
                        {permissions.edit &&
                            resource !== "contact-fields" &&
                            resource !== "members" &&
                            (bulkFields.find((field) => field.name === "status")
                                ?.editable ??
                                true) && (
                                <>
                                    <Select
                                        className="w-auto"
                                        disabled={filtering}
                                        aria-label={t("Status")}
                                        value={bulkStatus}
                                        onValueChange={(value) =>
                                            setBulkStatus(value)
                                        }
                                    >
                                        {statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => submitBulk("status")}
                                    >
                                        {t("Apply")}
                                    </Button>
                                </>
                            )}
                        {permissions.edit && (
                            <RegisteredBulkChange
                                fields={bulkFields}
                                name={bulkField}
                                value={bulkValue}
                                onNameChange={setBulkField}
                                onValueChange={setBulkValue}
                                onApply={() => submitBulk("change")}
                                busy={busy}
                            />
                        )}
                        {bulkError && (
                            <p
                                role="alert"
                                className="basis-full text-sm text-destructive"
                            >
                                {bulkError}
                            </p>
                        )}
                        {permissions.delete && (
                            <Button
                                variant="destructive"
                                size="sm"
                                disabled={busy}
                                onClick={() => setBulkDelete(true)}
                            >
                                {t("Delete selected")}
                            </Button>
                        )}
                    </div>
                )}
                <DataTableToolbar
                    loading={filtering}
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ search });
                    }}
                    tools={{
                        buttons: tableButtons,
                        columns,
                        visibility: tableVisibility
                            ? { ...tableVisibility, columns: visible }
                            : undefined,
                        onVisibilityChange: setVisible,
                        indexUrl: urls.index,
                        filters,
                        hasExport: resource === "contacts",
                    }}
                    tableActions={tableToolsExtra}
                >
                    <SearchInput
                        className="min-w-48 flex-1"
                        aria-label={t("Search")}
                        placeholder={t("Search")}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onClear={() => setSearch("")}
                    />
                    {resource !== "contact-fields" &&
                        resource !== "members" && (
                            <Select
                                disabled={filtering}
                                aria-label={t("Status")}
                                className="w-auto min-w-36"
                                value={filters.status ?? ""}
                                onValueChange={(value) =>
                                    visit({ status: value })
                                }
                            >
                                <option value="">{t("All statuses")}</option>
                                {statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                        )}
                    <Button
                        variant="outline"
                        type="submit"
                        disabled={filtering}
                    >
                        {t("Search")}
                    </Button>
                    {bulkFields.length > 0 && (
                        <Button
                            variant="outline"
                            onClick={() => setShowFilters(!showFilters)}
                            aria-expanded={showFilters}
                        >
                            {t("Advanced filters")}
                        </Button>
                    )}
                    {!!(
                        filters.search ||
                        filters.status ||
                        filters.advanced?.length
                    ) && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setSearch("");
                                setAdvanced([]);
                                visit({ search: "", status: "", advanced: [] });
                            }}
                        >
                            {t("Clear filters")}
                        </Button>
                    )}
                </DataTableToolbar>
                {showFilters && bulkFields.length > 0 && (
                    <fieldset className="contents" disabled={filtering}>
                        <RegisteredFilterEditor
                            fields={bulkFields}
                            value={advanced}
                            onChange={setAdvanced}
                            onApply={() => visit({ advanced })}
                        />
                    </fieldset>
                )}
                {bulkError && !selected.length && (
                    <p role="alert" className="p-4 text-sm text-destructive">
                        {bulkError}
                    </p>
                )}
                <div className="relative overflow-x-auto">
                    <Table className="w-full text-sm">
                        <TableHeader className="bg-muted/40 text-left text-xs text-muted-foreground">
                            <TableRow>
                                {canSelect && (
                                    <TableHead className="w-12 px-4 py-3">
                                        <Checkbox
                                            aria-label={t("Select all")}
                                            checked={
                                                items.data.length > 0 &&
                                                items.data.every((item) =>
                                                    selected.includes(item.id),
                                                )
                                            }
                                            indeterminate={
                                                selected.length > 0 &&
                                                !items.data.every((item) =>
                                                    selected.includes(item.id),
                                                )
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? items.data.map(
                                                              (item) => item.id,
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
                                        className="whitespace-nowrap px-4 py-3"
                                        aria-sort={
                                            column.sortable
                                                ? filters.sort === column.key
                                                    ? filters.direction ===
                                                      "asc"
                                                        ? "ascending"
                                                        : "descending"
                                                    : "none"
                                                : undefined
                                        }
                                    >
                                        {column.sortable ? (
                                            <button
                                                type="button"
                                                disabled={filtering}
                                                className="flex items-center gap-1 hover:text-foreground"
                                                onClick={() =>
                                                    visit({
                                                        sort: column.key,
                                                        direction:
                                                            filters.sort ===
                                                                column.key &&
                                                            filters.direction ===
                                                                "asc"
                                                                ? "desc"
                                                                : "asc",
                                                    })
                                                }
                                            >
                                                {t(column.label)}{" "}
                                                {filters.sort === column.key
                                                    ? filters.direction ===
                                                      "asc"
                                                        ? "↑"
                                                        : "↓"
                                                    : ""}
                                            </button>
                                        ) : (
                                            t(column.label)
                                        )}
                                    </TableHead>
                                ))}
                                <TableHead className="w-12 px-4 py-3">
                                    <span className="sr-only">
                                        {t("Actions")}
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.map((item) => (
                                <TableRow
                                    key={item.id}
                                    className="border-t hover:bg-muted/25"
                                >
                                    {canSelect && (
                                        <TableCell className="w-12 px-4 py-3">
                                            <Checkbox
                                                aria-label={
                                                    t("Select") +
                                                    " " +
                                                    item.name
                                                }
                                                checked={selected.includes(
                                                    item.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected(
                                                        checked === true
                                                            ? [
                                                                  ...selected,
                                                                  item.id,
                                                              ]
                                                            : selected.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      item.id,
                                                              ),
                                                    )
                                                }
                                            />
                                        </TableCell>
                                    )}
                                    {visibleColumns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className="px-4 py-3"
                                        >
                                            {column.key === "image" ? (
                                                <img
                                                    src={item.image}
                                                    alt=""
                                                    loading="lazy"
                                                    className={
                                                        resource === "members"
                                                            ? "size-10 rounded-full object-cover"
                                                            : "size-12 rounded border object-cover"
                                                    }
                                                />
                                            ) : column.key === "name" ||
                                              column.key === "title" ? (
                                                <>
                                                    {permissions.edit ? (
                                                        <Link
                                                            className="font-medium hover:underline"
                                                            href={item.edit_url}
                                                        >
                                                            {item.name}
                                                        </Link>
                                                    ) : (
                                                        item.name
                                                    )}
                                                    {item.email &&
                                                        !columns.some(
                                                            (column) =>
                                                                column.key ===
                                                                "email",
                                                        ) && (
                                                            <div className="mt-1 text-xs text-muted-foreground">
                                                                {item.email}
                                                            </div>
                                                        )}
                                                </>
                                            ) : column.key === "alias" ? (
                                                <div className="flex items-start gap-2">
                                                    <code className="min-w-0 select-all whitespace-pre-wrap break-all rounded bg-muted/40 px-2 py-1 text-xs">
                                                        {item.shortcode ??
                                                            item.alias ??
                                                            ""}
                                                    </code>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="shrink-0"
                                                        aria-label={`${t("Copy shortcode")}: ${item.name}`}
                                                        title={t(
                                                            "Copy shortcode",
                                                        )}
                                                        onClick={async () => {
                                                            try {
                                                                await navigator.clipboard.writeText(
                                                                    item.shortcode ??
                                                                        item.alias ??
                                                                        "",
                                                                );
                                                                notify(
                                                                    t("Copied"),
                                                                );
                                                            } catch {
                                                                notify(
                                                                    t(
                                                                        "Could not copy. Select and copy the code manually.",
                                                                    ),
                                                                    true,
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Copy />
                                                    </Button>
                                                </div>
                                            ) : column.key === "categories" ? (
                                                <div className="flex min-w-32 flex-wrap gap-x-2 gap-y-1">
                                                    {item.categories?.length
                                                        ? item.categories.map(
                                                              (
                                                                  category,
                                                                  index,
                                                              ) =>
                                                                  category.url ? (
                                                                      <Link
                                                                          key={
                                                                              index
                                                                          }
                                                                          href={
                                                                              category.url
                                                                          }
                                                                          className="hover:underline"
                                                                      >
                                                                          {
                                                                              category.name
                                                                          }
                                                                      </Link>
                                                                  ) : (
                                                                      <span
                                                                          key={
                                                                              index
                                                                          }
                                                                      >
                                                                          {
                                                                              category.name
                                                                          }
                                                                      </span>
                                                                  ),
                                                          )
                                                        : "—"}
                                                </div>
                                            ) : column.key === "author" ? (
                                                item.author_url ? (
                                                    <a
                                                        href={item.author_url}
                                                        className="hover:underline"
                                                    >
                                                        {item.author || "—"}
                                                    </a>
                                                ) : (
                                                    item.author || "—"
                                                )
                                            ) : column.key === "email" ? (
                                                item.email ? (
                                                    <a
                                                        href={
                                                            "mailto:" +
                                                            item.email
                                                        }
                                                        className="hover:underline"
                                                    >
                                                        {item.email}
                                                    </a>
                                                ) : (
                                                    "—"
                                                )
                                            ) : column.key === "phone" ? (
                                                item.phone ? (
                                                    <a
                                                        href={
                                                            "tel:" + item.phone
                                                        }
                                                        className="whitespace-nowrap hover:underline"
                                                    >
                                                        {item.phone}
                                                    </a>
                                                ) : (
                                                    "—"
                                                )
                                            ) : column.key === "type" ? (
                                                <Badge variant="outline">
                                                    {item.type_label ??
                                                        item.type ??
                                                        "—"}
                                                </Badge>
                                            ) : column.key === "status" ? (
                                                <Badge
                                                    variant={
                                                        item.status ===
                                                        "published"
                                                            ? "success"
                                                            : "outline"
                                                    }
                                                >
                                                    {item.status_label}
                                                </Badge>
                                            ) : column.key ===
                                              "confirmed_at" ? (
                                                <Badge
                                                    variant={
                                                        item.confirmed_at
                                                            ? "success"
                                                            : "outline"
                                                    }
                                                >
                                                    {t(
                                                        item.confirmed_at
                                                            ? "Yes"
                                                            : "No",
                                                    )}
                                                </Badge>
                                            ) : column.key === "created_at" ? (
                                                <span className="whitespace-nowrap text-muted-foreground">
                                                    {item.created_at
                                                        ? formatDate(
                                                              item.created_at,
                                                          )
                                                        : "—"}
                                                </span>
                                            ) : column.key ===
                                              "locations_display" ? (
                                                item.locations_display || "—"
                                            ) : column.key === "items_count" ? (
                                                (item.items_count ?? 0)
                                            ) : column.key === "order" ? (
                                                item.order
                                            ) : (
                                                <span className="tabular-nums text-muted-foreground">
                                                    {item.id}
                                                </span>
                                            )}
                                        </TableCell>
                                    ))}
                                    <TableCell className="px-4 py-2">
                                        {(item.export_url ||
                                            permissions.edit ||
                                            permissions.delete) && (
                                            <DropdownMenu label={t("Actions")}>
                                                {item.export_url && (
                                                    <DropdownMenuItem asChild>
                                                        <a
                                                            href={
                                                                item.export_url
                                                            }
                                                        >
                                                            {t("Export")}
                                                        </a>
                                                    </DropdownMenuItem>
                                                )}
                                                {permissions.edit && (
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={item.edit_url}
                                                        >
                                                            <Pencil />
                                                            {t("Edit")}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                {permissions.delete && (
                                                    <DropdownMenuItem
                                                        destructive
                                                        onSelect={() =>
                                                            setTarget(item)
                                                        }
                                                    >
                                                        <Trash2 />
                                                        {t("Delete")}
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenu>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {!items.data.length && (
                    <div className="flex flex-col items-center gap-3 p-12 text-center text-muted-foreground">
                        <Files className="size-8" />
                        <p>{t("No results found")}</p>
                    </div>
                )}
                <TablePagination
                    loading={filtering}
                    total={items.total}
                    previous={items.prev_page_url}
                    next={items.next_page_url}
                    currentPage={items.current_page}
                    lastPage={items.last_page}
                    perPage={filters.per_page ?? 20}
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
                open={Boolean(target) || bulkDelete}
                onOpenChange={(open) =>
                    !busy && !open && (setTarget(null), setBulkDelete(false))
                }
                title={t("Delete")}
                description={
                    bulkDelete
                        ? `${selected.length} ${t("selected")}`
                        : target?.name
                }
                size="sm"
            >
                <p className="text-sm text-muted-foreground">
                    {t("This action cannot be undone.")}
                </p>
                <div className="mt-6 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => {
                            setTarget(null);
                            setBulkDelete(false);
                        }}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        loading={busy}
                        onClick={() => {
                            if (bulkDelete) {
                                submitBulk("delete");
                                return;
                            }
                            if (!target) return;
                            setBusy(true);
                            router.delete(target.delete_url, {
                                onSuccess: () => setTarget(null),
                                onFinish: () => setBusy(false),
                            });
                        }}
                    >
                        {t("Delete")}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
