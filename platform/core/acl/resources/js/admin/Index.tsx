import { useTableNavigation } from "@admin/hooks/use-table-navigation";
import {
    RegisteredFilterEditor,
    type RegisteredFilter,
} from "@admin/components/registered-table-controls";
import { TablePagination } from "@admin/components/table-pagination";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
import AdminLayout from "@admin/layouts/admin-layout";
import { tableActionUrl } from "@admin/lib/table-query";
import { useAdminTranslation } from "@admin/lib/translate";
import { adminQueryOptions } from "@admin/lib/utils";
import { Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { AclBulkActions } from "./_components/acl-bulk";
import { AclFilters } from "./_components/acl-filters";
import { AclTable } from "./_components/acl-table";
import { useRoleAssignment } from "./hooks/use-role-assignment";
import type { Props, Row } from "./lib/acl-types";

export default function Index({
    roleOptions = [],
    assignRoleUrl,
    title,
    kind,
    search: initial,
    items,
    urls,
    filters = {},
    canStatus = false,
    bulkFields = [],
    tableButtons = ["reload"],
    tableVisibility,
}: Props) {
    const t = useAdminTranslation();
    const { loading: filtering, get: tableGet } = useTableNavigation();
    const { roleValues, roleBusy, roleErrors, assignRole } = useRoleAssignment(
        items,
        assignRoleUrl,
        roleOptions,
    );
    const [visible, setVisible] = useState(tableVisibility?.columns ?? {});
    useEffect(
        () => setVisible(tableVisibility?.columns ?? {}),
        [tableVisibility],
    );
    const columns =
        kind === "users"
            ? [
                  { key: "username", label: "Username" },
                  { key: "email", label: "Email" },
                  { key: "role_name", label: "Roles" },
                  { key: "created_at", label: "Created at" },
                  { key: "status_name", label: "Status" },
                  { key: "super_user", label: "Super user" },
              ]
            : [
                  { key: "name", label: "Name" },
                  { key: "description", label: "Description" },
                  { key: "id", label: "ID" },
                  { key: "created_at", label: "Created at" },
                  { key: "created_by", label: "Created by" },
              ];
    const hidden = (key: string) => visible[key] === 0;

    const [search, setSearch] = useState(initial);
    const [target, setTarget] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkAction, setBulkAction] = useState<string | null>(null);
    const [error, setError] = useState("");
    const [bulkField, setBulkField] = useState("");
    const [bulkValue, setBulkValue] = useState("");
    const [advanced, setAdvanced] = useState<RegisteredFilter[]>(
        filters.advanced ?? [],
    );
    const [showFilters, setShowFilters] = useState(
        Boolean(filters.advanced?.length),
    );
    const selectable = items.data.filter(
        (row) => row.delete_url || row.can_status || row.can_change,
    );
    const selectionContext = JSON.stringify(filters);
    useEffect(() => {
        setSelected([]);
        setBulkAction(null);
        setError("");
        setSearch(initial);
        setAdvanced(filters.advanced ?? []);
    }, [items.data, selectionContext, initial, filters.advanced]);
    function visit(next: Record<string, string | number | RegisteredFilter[]>) {
        tableGet(
            urls.index,
            { ...filters, search, ...next, page: 1 },
            {
                ...adminQueryOptions,
                preserveState: true,
                onError: (errors) => setError(Object.values(errors).join(" ")),
            },
        );
    }
    function sort(column: string) {
        visit({
            sort: column,
            direction:
                filters.sort === column && filters.direction === "asc"
                    ? "desc"
                    : "asc",
        });
    }
    return (
        <AdminLayout
            title={t(title)}
            actions={
                urls.create && (
                    <Button asChild>
                        <Link href={urls.create}>{t("Create")}</Link>
                    </Button>
                )
            }
        >
            <Card aria-busy={filtering}>
                <AclFilters
                    loading={filtering}
                    visit={visit}
                    setSearch={setSearch}
                    search={search}
                    kind={kind}
                    filters={filters}
                    setShowFilters={setShowFilters}
                    showFilters={showFilters}
                    setAdvanced={setAdvanced}
                    tableTools={{
                        buttons: tableButtons,
                        columns,
                        visibility: tableVisibility
                            ? { ...tableVisibility, columns: visible }
                            : undefined,
                        onVisibilityChange: setVisible,
                        indexUrl: urls.index,
                        filters: { ...filters, search: initial },
                    }}
                />
                {selected.length > 0 && (
                    <AclBulkActions
                        selected={selected}
                        canStatus={canStatus}
                        busy={busy}
                        items={items}
                        setError={setError}
                        setBulkAction={setBulkAction}
                        bulkFields={bulkFields}
                        bulkField={bulkField}
                        bulkValue={bulkValue}
                        setBulkField={setBulkField}
                        setBulkValue={setBulkValue}
                        setSelected={setSelected}
                    />
                )}
                {showFilters && (
                    <fieldset className="contents" disabled={filtering}>
                        <RegisteredFilterEditor
                            fields={bulkFields}
                            value={advanced}
                            onChange={setAdvanced}
                            onApply={() => visit({ advanced })}
                        />
                    </fieldset>
                )}
                {error && !bulkAction && (
                    <p role="alert" className="p-4 text-sm text-destructive">
                        {error}
                    </p>
                )}
                <div className="overflow-x-auto">
                    <AclTable
                        selectable={selectable}
                        busy={busy}
                        selected={selected}
                        setSelected={setSelected}
                        hidden={hidden}
                        kind={kind}
                        filters={filters}
                        sort={sort}
                        items={items}
                        roleOptions={roleOptions}
                        roleBusy={roleBusy}
                        roleValues={roleValues}
                        assignRole={assignRole}
                        roleErrors={roleErrors}
                        setTarget={setTarget}
                    />
                </div>
                <TablePagination
                    loading={filtering}
                    total={items.total}
                    previous={items.prev_page_url}
                    next={items.next_page_url}
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
                open={!!bulkAction}
                onOpenChange={(open) => !open && !busy && setBulkAction(null)}
                title={t(
                    bulkAction === "change"
                        ? "Change selected items"
                        : bulkAction === "delete"
                          ? "Delete selected items"
                          : bulkAction === "activated"
                            ? "Activate selected users"
                            : "Deactivate selected users",
                )}
            >
                <p className="text-sm text-muted-foreground">
                    {selected.length} {t("selected")}.{" "}
                    {t("Please confirm this action.")}
                </p>
                {error && (
                    <p role="alert" className="mt-3 text-sm text-destructive">
                        {error}
                    </p>
                )}
                <div className="mt-5 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setBulkAction(null)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant={
                            bulkAction === "delete" ? "destructive" : "default"
                        }
                        loading={busy}
                        onClick={() => {
                            setBusy(true);
                            setError("");
                            router.post(
                                tableActionUrl(urls.bulk, {
                                    ...filters,
                                    search: initial,
                                    page: items.current_page,
                                }),
                                {
                                    ids: selected,
                                    action:
                                        bulkAction === "change"
                                            ? "change"
                                            : bulkAction === "delete"
                                              ? "delete"
                                              : "status",
                                    ...(bulkAction === "change"
                                        ? { field: bulkField, value: bulkValue }
                                        : bulkAction !== "delete"
                                          ? { status: bulkAction }
                                          : {}),
                                },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setSelected([]);
                                        setBulkAction(null);
                                    },
                                    onError: (errors) =>
                                        setError(
                                            Object.values(errors).join(" "),
                                        ),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        {t("Confirm")}
                    </Button>
                </div>
            </Dialog>
            <Dialog
                open={!!target}
                onOpenChange={(open) => !open && !busy && setTarget(null)}
                title={t("Delete")}
                description={target?.name}
            >
                <p className="text-sm text-muted-foreground">
                    {t("This action cannot be undone.")}
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setTarget(null)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        loading={busy}
                        onClick={() => {
                            if (!target?.delete_url) return;
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
