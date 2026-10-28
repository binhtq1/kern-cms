import { SearchInput } from "@admin/components/search-input";
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import { notify } from "@admin/components/toast";
import { useState } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, Database, LockKeyhole, Search } from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Checkbox } from "@admin/components/ui/checkbox";

import { Card, CardHeader, CardTitle } from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
import { useAdminTranslation } from "@admin/lib/translate";

type Table = { name: string; count: number; protected: boolean };
export default function Cleanup({
    title,
    warning,
    disabledMessage,
    enabled,
    submit,
    tables,
}: {
    title: string;
    warning: string;
    disabledMessage: string;
    enabled: boolean;
    submit: string;
    tables: Table[];
}) {
    const t = useAdminTranslation();
    const [kept, setKept] = useState(
        tables.filter((table) => table.protected).map((table) => table.name),
    );
    const [search, setSearch] = useState("");
    const [confirm, setConfirm] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState("");
    const visible = tables.filter((table) =>
        table.name.toLowerCase().includes(search.toLowerCase()),
    );
    const removable = tables.filter(
        (table) => !table.protected && !kept.includes(table.name),
    );
    const eligible = visible.filter((table) => !table.protected);
    async function clean() {
        setBusy(true);
        setMessage("");
        try {
            const response = await axios.post(submit, { tables: kept });
            if (response.data.error) throw new Error(response.data.message);
            notify(response.data.message);
            setConfirm(false);
            router.reload({ only: ["tables"] });
        } catch (error) {
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
                      : t("Cleanup failed"),
            );
        } finally {
            setBusy(false);
        }
    }
    return (
        <AdminLayout title={title}>
            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex gap-3 rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600" />
                    <div className="min-w-0 space-y-2 [overflow-wrap:anywhere]">
                        <p>{warning}</p>
                        {!enabled && (
                            <p className="text-muted-foreground">
                                {disabledMessage}
                            </p>
                        )}
                    </div>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>{t("Choose tables to keep")}</CardTitle>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            {t(
                                "Checked tables are preserved. Unchecked tables will be cleared.",
                            )}
                        </p>
                    </CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
                        <label className="flex items-center gap-1 text-sm">
                            <Checkbox
                                checked={
                                    eligible.length > 0 &&
                                    eligible.every((table) =>
                                        kept.includes(table.name),
                                    )
                                }
                                indeterminate={
                                    eligible.some((table) =>
                                        kept.includes(table.name),
                                    ) &&
                                    !eligible.every((table) =>
                                        kept.includes(table.name),
                                    )
                                }
                                disabled={busy || !eligible.length}
                                onCheckedChange={(checked) =>
                                    setKept((current) =>
                                        checked === true
                                            ? [
                                                  ...new Set([
                                                      ...current,
                                                      ...eligible.map(
                                                          (table) => table.name,
                                                      ),
                                                  ]),
                                              ]
                                            : current.filter(
                                                  (name) =>
                                                      !eligible.some(
                                                          (table) =>
                                                              table.name ===
                                                              name,
                                                      ),
                                              ),
                                    )
                                }
                            />
                            {t("Keep all visible tables")}
                        </label>
                        <SearchInput
                            className="w-full sm:w-64"
                            aria-label={t("Search tables")}
                            placeholder={t("Search tables")}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onClear={() => setSearch("")}
                            onKeyDown={(event) => {
                                if (event.key === "Escape") setSearch("");
                            }}
                        />
                    </div>
                    <div className="max-h-[32rem] overflow-auto">
                        <Table className="w-full text-left text-sm">
                            <TableHeader className="sticky top-0 z-10 border-b bg-muted">
                                <TableRow>
                                    <TableHead className="w-14 px-4 py-3">
                                        {t("Keep")}
                                    </TableHead>
                                    <TableHead className="px-4 py-3">
                                        {t("Table name")}
                                    </TableHead>
                                    <TableHead className="px-5 py-3 text-right">
                                        {t("Records")}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="divide-y">
                                {visible.map((table) => (
                                    <TableRow
                                        key={table.name}
                                        className="hover:bg-muted/30"
                                    >
                                        <TableCell className="px-4 py-2">
                                            <Checkbox
                                                aria-label={`${t("Keep")} ${table.name}`}
                                                checked={
                                                    table.protected ||
                                                    kept.includes(table.name)
                                                }
                                                disabled={
                                                    busy || table.protected
                                                }
                                                onCheckedChange={(checked) =>
                                                    setKept((current) =>
                                                        checked === true
                                                            ? [
                                                                  ...new Set([
                                                                      ...current,
                                                                      table.name,
                                                                  ]),
                                                              ]
                                                            : current.filter(
                                                                  (name) =>
                                                                      name !==
                                                                      table.name,
                                                              ),
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="px-4 py-2">
                                            <label className="flex items-center gap-2">
                                                <Database
                                                    size={15}
                                                    className="text-muted-foreground"
                                                />
                                                <span className="break-all font-mono text-xs">
                                                    {table.name}
                                                </span>
                                                {table.protected && (
                                                    <span
                                                        title={t(
                                                            "Protected table",
                                                        )}
                                                    >
                                                        <LockKeyhole
                                                            size={14}
                                                            className="text-muted-foreground"
                                                        />
                                                    </span>
                                                )}
                                            </label>
                                        </TableCell>
                                        <TableCell className="px-5 py-2 text-right tabular-nums">
                                            {table.count.toLocaleString()}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {!visible.length && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="px-5 py-8 text-center text-muted-foreground"
                                        >
                                            {t("No tables found")}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/20 px-5 py-4">
                        <p className="text-xs text-muted-foreground">
                            {t(":count tables will be cleared", {
                                count: removable.length,
                            })}
                        </p>
                        <Button
                            variant="destructive"
                            disabled={!enabled || !removable.length || busy}
                            onClick={() => {
                                setMessage("");
                                setConfirm(true);
                            }}
                        >
                            <AlertTriangle size={16} />
                            {t("Clean database")}
                        </Button>
                    </div>
                </Card>
                {message && !confirm && (
                    <p role="status" className="rounded-md border p-3 text-sm">
                        {message}
                    </p>
                )}
            </div>
            <Dialog
                open={confirm}
                onOpenChange={(open) => {
                    if (!busy) setConfirm(open);
                }}
                title={t("Confirm database cleanup")}
                description={t(
                    "Unchecked tables will be permanently cleared. Take a backup before continuing.",
                )}
                size="sm"
            >
                <p className="mb-4 text-sm">
                    {t(":count tables will be cleared", {
                        count: removable.length,
                    })}
                </p>
                <div className="max-h-40 overflow-auto rounded-md border bg-muted/30 p-3 font-mono text-xs">
                    {removable.map((table) => (
                        <p key={table.name}>{table.name}</p>
                    ))}
                </div>
                {message && (
                    <p role="alert" className="mt-3 text-sm text-destructive">
                        {message}
                    </p>
                )}
                <div className="mt-5 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setConfirm(false)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        loading={busy}
                        onClick={() => void clean()}
                    >
                        {t("Clean database")}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
