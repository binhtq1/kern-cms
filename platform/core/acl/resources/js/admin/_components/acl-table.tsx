import { type RegisteredFilter } from "@admin/components/registered-table-controls";
import { Badge } from "@admin/components/ui/badge";
import { LoadingIndicator } from "@admin/components/loading-indicator";
import { Button } from "@admin/components/ui/button";
import { Checkbox } from "@admin/components/ui/checkbox";
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
import { Link } from "@inertiajs/react";

import type { Dispatch, SetStateAction } from "react";
import type { Row } from "../lib/acl-types";
export function AclTable({
    selectable,
    busy,
    selected,
    setSelected,
    hidden,
    kind,
    filters,
    sort,
    items,
    roleOptions,
    roleBusy,
    roleValues,
    assignRole,
    roleErrors,
    setTarget,
}: {
    selectable: Row[];
    busy: boolean;
    selected: number[];
    setSelected: Dispatch<SetStateAction<number[]>>;
    hidden: (key: string) => boolean;
    kind: string;
    filters: {
        advanced?: RegisteredFilter[] | undefined;
        status?: string | undefined;
        sort?: string | undefined;
        direction?: string | undefined;
        per_page?: number | undefined;
    };
    sort: (column: string) => void;
    items: {
        data: Row[];
        total: number;
        current_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    roleOptions: { value: string; label: string }[];
    roleBusy: Record<number, boolean>;
    roleValues: Record<number, string>;
    assignRole: (row: Row, value: string) => Promise<void>;
    roleErrors: Record<number, string>;
    setTarget: Dispatch<SetStateAction<Row | null>>;
}) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    return (
        <Table className="w-full text-left text-sm">
            <TableHeader className="border-b bg-muted/40 text-muted-foreground">
                <TableRow>
                    <TableHead className="w-10 p-3">
                        <Checkbox
                            aria-label={t("Select all")}
                            disabled={!selectable.length || busy}
                            checked={
                                selectable.length > 0 &&
                                selected.length === selectable.length
                            }
                            indeterminate={
                                selected.length > 0 &&
                                selected.length < selectable.length
                            }
                            onCheckedChange={(checked) =>
                                setSelected(
                                    checked === true
                                        ? selectable.map((row) => row.id)
                                        : [],
                                )
                            }
                        />
                    </TableHead>
                    <TableHead
                        hidden={hidden(kind === "users" ? "username" : "name")}
                        className="p-3"
                        aria-sort={
                            filters.sort ===
                            (kind === "users" ? "username" : "name")
                                ? filters.direction === "desc"
                                    ? "descending"
                                    : "ascending"
                                : "none"
                        }
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                sort(kind === "users" ? "username" : "name")
                            }
                        >
                            {t(kind === "users" ? "Username" : "Name")}
                        </Button>
                    </TableHead>
                    <TableHead
                        hidden={hidden(
                            kind === "users" ? "email" : "description",
                        )}
                        className="p-3"
                    >
                        {t(kind === "users" ? "Email" : "Description")}
                    </TableHead>
                    <TableHead
                        hidden={hidden(kind === "users" ? "role_name" : "id")}
                        className="p-3"
                    >
                        {t(kind === "users" ? "Roles" : "ID")}
                    </TableHead>
                    <TableHead
                        hidden={hidden("created_at")}
                        className="p-3"
                        aria-sort={
                            filters.sort === "created_at"
                                ? filters.direction === "desc"
                                    ? "descending"
                                    : "ascending"
                                : "none"
                        }
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => sort("created_at")}
                        >
                            {t("Created at")}
                        </Button>
                    </TableHead>
                    {kind === "users" ? (
                        <>
                            <TableHead
                                hidden={hidden("status_name")}
                                className="p-3"
                            >
                                {t("Status")}
                            </TableHead>
                            <TableHead
                                hidden={hidden("super_user")}
                                className="p-3"
                            >
                                {t("Super user")}
                            </TableHead>
                        </>
                    ) : (
                        <TableHead
                            hidden={hidden("created_by")}
                            className="p-3"
                        >
                            {t("Created by")}
                        </TableHead>
                    )}
                    <TableHead className="p-3 text-right">
                        {t("Actions")}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {items.data.map((row) => (
                    <TableRow
                        key={row.id}
                        className="border-b last:border-0 hover:bg-muted/30"
                    >
                        <TableCell className="p-3">
                            <Checkbox
                                aria-label={`${t("Select")} ${row.username ?? row.name}`}
                                checked={selected.includes(row.id)}
                                disabled={
                                    busy ||
                                    (!row.delete_url &&
                                        !row.can_status &&
                                        !row.can_change)
                                }
                                onCheckedChange={(checked) =>
                                    setSelected(
                                        checked === true
                                            ? [...selected, row.id]
                                            : selected.filter(
                                                  (id) => id !== row.id,
                                              ),
                                    )
                                }
                            />
                        </TableCell>
                        <TableCell
                            hidden={hidden(
                                kind === "users" ? "username" : "name",
                            )}
                            className="p-3 font-medium"
                        >
                            {row.edit_url ? (
                                <Link
                                    href={row.edit_url}
                                    className="hover:underline"
                                >
                                    {row.username ?? row.name}
                                </Link>
                            ) : (
                                (row.username ?? row.name)
                            )}
                        </TableCell>
                        <TableCell
                            hidden={hidden(
                                kind === "users" ? "email" : "description",
                            )}
                            className="p-3 text-muted-foreground"
                        >
                            {row.email ?? row.description}
                        </TableCell>
                        <TableCell
                            hidden={hidden(
                                kind === "users" ? "role_name" : "id",
                            )}
                            className="p-3"
                        >
                            {kind === "users" &&
                            row.can_assign_role &&
                            roleOptions.length > 0 ? (
                                <div
                                    className="min-w-40 space-y-2"
                                    aria-busy={roleBusy[row.id]}
                                >
                                    <Select
                                        aria-label={t("Role for :name", {
                                            name: row.name,
                                        })}
                                        value={
                                            roleValues[row.id] ??
                                            row.role_id ??
                                            ""
                                        }
                                        disabled={roleBusy[row.id]}
                                        onValueChange={(value) =>
                                            void assignRole(row, value)
                                        }
                                    >
                                        <option value="" disabled>
                                            {t("No role assigned")}
                                        </option>
                                        {row.role_id &&
                                            !roleOptions.some(
                                                (option) =>
                                                    option.value ===
                                                    row.role_id,
                                            ) && (
                                                <option
                                                    value={row.role_id}
                                                    disabled
                                                >
                                                    {row.roles?.join(", ")}
                                                </option>
                                            )}
                                        {roleOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                    {roleBusy[row.id] && (
                                        <LoadingIndicator
                                            label={t("Saving role…")}
                                            iconClassName="size-3.5"
                                        />
                                    )}
                                    {roleErrors[row.id] && (
                                        <p
                                            role="alert"
                                            className="text-xs text-destructive"
                                        >
                                            {roleErrors[row.id]}
                                        </p>
                                    )}
                                </div>
                            ) : kind === "users" ? (
                                row.roles?.join(", ") || t("No role assigned")
                            ) : (
                                row.id
                            )}
                        </TableCell>
                        <TableCell
                            hidden={hidden("created_at")}
                            className="whitespace-nowrap p-3 text-muted-foreground"
                        >
                            {row.created_at ? formatDate(row.created_at) : "—"}
                        </TableCell>
                        {kind === "users" ? (
                            <>
                                <TableCell
                                    hidden={hidden("status_name")}
                                    className="p-3"
                                >
                                    <Badge
                                        variant={
                                            row.activated
                                                ? "success"
                                                : "warning"
                                        }
                                    >
                                        {t(
                                            row.activated
                                                ? "Activated"
                                                : "Deactivated",
                                        )}
                                    </Badge>
                                </TableCell>
                                <TableCell
                                    hidden={hidden("super_user")}
                                    className="p-3"
                                >
                                    {t(row.super_user ? "Yes" : "No")}
                                </TableCell>
                            </>
                        ) : (
                            <TableCell
                                hidden={hidden("created_by")}
                                className="p-3"
                            >
                                {row.author_url ? (
                                    <Link
                                        href={row.author_url}
                                        className="text-primary-ink hover:underline"
                                    >
                                        {row.author || "—"}
                                    </Link>
                                ) : (
                                    row.author || "—"
                                )}
                            </TableCell>
                        )}
                        <TableCell className="p-3 text-right">
                            {row.delete_url && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="text-destructive"
                                    onClick={() => setTarget(row)}
                                >
                                    {t("Delete")}
                                </Button>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
                {items.data.length === 0 && (
                    <TableRow>
                        <TableCell
                            colSpan={kind === "users" ? 8 : 7}
                            className="p-10 text-center text-muted-foreground"
                        >
                            {t("No results found")}
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
