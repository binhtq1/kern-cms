import { useTableNavigation } from "@admin/hooks/use-table-navigation";
import { DataTableToolbar } from "@admin/components/data-table-toolbar";
import { SearchInput } from "@admin/components/search-input";
import { TablePagination } from "@admin/components/table-pagination";
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import { parseJsonImport } from "@admin/lib/json-import";
import { adminQueryOptions } from "@admin/lib/utils";
import {
    DropdownMenu,
    DropdownMenuItem,
} from "@admin/components/ui/dropdown-menu";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { Dialog } from "@admin/components/ui/dialog";
import { Field } from "@admin/components/field";
import { TranslationNavigation } from "../components/translation-navigation";
type Row = { group: string; key: string; original: string; value: string };
type Props = {
    theme: boolean;
    locale: string;
    locales: { locale: string; name: string }[];
    groups: string[];
    items: {
        data: Row[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url?: string;
        next_page_url?: string;
    };
    filters: { search?: string; group?: string; per_page?: number };
    permissions: { export: boolean; import: boolean };
    instruction?: string;
    reimportDescription?: string;
    urls: {
        index: string;
        reimport?: string | null;
        update: string;
        admin: string;
        theme: string;
        locales: string;
        dataExport: string;
        dataImport: string;
        export: string;
        import: string;
    };
};
export default function TranslationIndex({
    theme,
    locale,
    locales,
    groups,
    items,
    filters,
    permissions,
    urls,
    instruction,
    reimportDescription,
}: Props) {
    const t = useAdminTranslation();
    const { loading: filtering, get: tableGet } = useTableNavigation();
    const title = t(theme ? "Theme translations" : "Admin translations");
    const [search, setSearch] = useState(filters.search ?? "");
    const [target, setTarget] = useState<Row | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [json, setJson] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const [reimportOpen, setReimportOpen] = useState(false);
    const form = useForm({ name: "", value: "", group: "", pk: locale });
    function visit(changes: Record<string, string | number>) {
        tableGet(
            urls.index,
            { ...filters, ref_lang: locale, ...changes, page: 1 },
            {
                ...adminQueryOptions,
                preserveState: true,
                preserveScroll: true,
            },
        );
    }
    return (
        <AdminLayout
            title={title}
            actions={
                <>
                    {theme && urls.reimport && (
                        <Button
                            variant="outline"
                            onClick={() => setReimportOpen(true)}
                        >
                            {t("Refresh translations from theme files")}
                        </Button>
                    )}
                    {permissions.export && (
                        <Button asChild variant="outline">
                            <Link href={urls.dataExport}>{t("Export")}</Link>
                        </Button>
                    )}
                    {permissions.import && (
                        <Button asChild variant="outline">
                            <Link href={urls.dataImport}>{t("Import")}</Link>
                        </Button>
                    )}
                    {(permissions.export || permissions.import) && (
                        <DropdownMenu label={t("JSON tools")} trigger="JSON">
                            {permissions.export && (
                                <DropdownMenuItem asChild>
                                    <a href={urls.export}>{t("Export JSON")}</a>
                                </DropdownMenuItem>
                            )}
                            {permissions.import && (
                                <DropdownMenuItem
                                    onClick={() => setImportOpen(true)}
                                >
                                    {t("Import JSON")}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenu>
                    )}
                </>
            }
        >
            <Head title={title} />
            <TranslationNavigation
                active={theme ? "theme" : "admin"}
                urls={urls}
            />
            {instruction && (
                <div
                    role="status"
                    className="mb-5 space-y-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground"
                >
                    <p>{instruction}</p>
                </div>
            )}
            <Card aria-busy={filtering}>
                <DataTableToolbar
                    loading={filtering}
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ search });
                    }}
                    tools={{
                        buttons: ["reload"],
                        columns: [
                            { key: "group", label: "Group" },
                            { key: "key", label: "Key" },
                            { key: "original", label: "Original" },
                            { key: "value", label: "Translation" },
                        ],
                        onVisibilityChange: () => undefined,
                        indexUrl: urls.index,
                        filters: { ...filters, ref_lang: locale },
                    }}
                >
                    <SearchInput
                        onClear={() => {
                            setSearch("");
                        }}
                        className="min-w-40 flex-1"
                        aria-label={t("Search")}
                        placeholder={t("Search")}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select
                        className="w-auto"
                        disabled={filtering}
                        aria-label={t("Locale")}
                        value={locale}
                        onValueChange={(value) => visit({ ref_lang: value })}
                    >
                        {locales.map((item) => (
                            <option key={item.locale} value={item.locale}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                    {!theme && (
                        <Select
                            className="max-w-72"
                            disabled={filtering}
                            aria-label={t("Group")}
                            value={filters.group ?? ""}
                            onValueChange={(value) => visit({ group: value })}
                        >
                            <option value="">{t("All groups")}</option>
                            {groups.map((group) => (
                                <option key={group}>{group}</option>
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
                    {!!(filters.search || filters.group) && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setSearch("");
                                visit({ search: "", group: "" });
                            }}
                        >
                            {t("Clear filters")}
                        </Button>
                    )}
                </DataTableToolbar>
                <div className="overflow-x-auto">
                    <Table className="min-w-[48rem] text-left">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-1/3">
                                    {t("Original")}
                                </TableHead>
                                <TableHead>{t("Translation")}</TableHead>
                                <TableHead className="w-24">
                                    {t("Actions")}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.map((row) => (
                                <TableRow key={row.group + "|" + row.key}>
                                    <TableCell className="break-words">
                                        <p>{row.original}</p>
                                        <p className="mt-1 break-all font-mono text-xs text-muted-foreground">
                                            {row.group} / {row.key}
                                        </p>
                                    </TableCell>
                                    <TableCell className="whitespace-pre-wrap break-words">
                                        <span
                                            className={
                                                theme && row.key === row.value
                                                    ? "text-primary-ink"
                                                    : undefined
                                            }
                                        >
                                            {row.value}
                                            {theme && row.key === row.value && (
                                                <span className="sr-only">
                                                    {" "}
                                                    ({t("Untranslated")})
                                                </span>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                form.setData({
                                                    name: theme
                                                        ? row.key
                                                        : `${locale}|${row.key}`,
                                                    group: row.group,
                                                    value: row.value,
                                                    pk: locale,
                                                });
                                                form.clearErrors();
                                                setTarget(row);
                                            }}
                                        >
                                            {t("Edit")}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!items.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        {t("No results found")}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <TablePagination
                    loading={filtering}
                    total={items.total}
                    previous={items.prev_page_url}
                    next={items.next_page_url}
                    currentPage={items.current_page}
                    lastPage={items.last_page}
                    perPage={filters.per_page ?? 50}
                    onPageSize={(per_page) => visit({ per_page })}
                    onVisit={(url) =>
                        tableGet(
                            url,
                            {},
                            { ...adminQueryOptions, preserveScroll: true },
                        )
                    }
                />
            </Card>
            <Dialog
                open={reimportOpen}
                onOpenChange={(open) => !busy && setReimportOpen(open)}
                title={t("Refresh translations from theme files")}
                description={reimportDescription}
            >
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setReimportOpen(false)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        loading={busy}
                        onClick={() => {
                            if (!urls.reimport) return;
                            setBusy(true);
                            router.post(
                                urls.reimport,
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setReimportOpen(false),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        {t("Continue")}
                    </Button>
                </div>
            </Dialog>
            <Dialog
                open={Boolean(target)}
                onOpenChange={(open) =>
                    !form.processing && !open && setTarget(null)
                }
                title={t("Edit translation")}
                description={target?.original}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(urls.update, {
                            preserveScroll: true,
                            onSuccess: () => setTarget(null),
                        });
                    }}
                >
                    <Field
                        label={t("Translation")}
                        error={form.errors.value || form.errors.name}
                    >
                        {(id) => (
                            <Textarea
                                id={id}
                                className="min-h-40"
                                value={form.data.value}
                                onChange={(event) =>
                                    form.setData("value", event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Button
                        type="submit"
                        className="mt-4"
                        loading={form.processing}
                    >
                        {t("Save")}
                    </Button>
                    {Object.values(form.errors).map((error) => (
                        <p
                            key={error}
                            className="text-sm text-destructive"
                            role="alert"
                        >
                            {error}
                        </p>
                    ))}
                </form>
            </Dialog>
            <Dialog
                open={importOpen}
                onOpenChange={(value) => !busy && setImportOpen(value)}
                title={t("Import")}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        try {
                            const rows = parseJsonImport(
                                json,
                                20 * 1024 * 1024,
                                50000,
                            );
                            if (!Array.isArray(rows)) throw new Error();
                            setError("");
                            setBusy(true);
                            router.post(
                                urls.import,
                                { rows },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setImportOpen(false),
                                    onError: (errors) =>
                                        setError(Object.values(errors)[0]),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        } catch {
                            setError(t("Invalid JSON"));
                        }
                    }}
                >
                    <Field label={t("JSON file")}>
                        {(id) => (
                            <Input
                                id={id}
                                type="file"
                                accept=".json,application/json"
                                disabled={busy}
                                onChange={async (event) => {
                                    const file = event.target.files?.[0];
                                    event.target.value = "";
                                    if (!file) return;
                                    setBusy(true);
                                    setError("");
                                    try {
                                        if (file.size > 20 * 1024 * 1024)
                                            throw new Error(
                                                "File is too large",
                                            );
                                        const text = await file.text();
                                        parseJsonImport(
                                            text,
                                            20 * 1024 * 1024,
                                            50000,
                                        );
                                        setJson(text);
                                    } catch (error) {
                                        setError(
                                            t(
                                                error instanceof Error
                                                    ? error.message
                                                    : "Invalid JSON",
                                            ),
                                        );
                                    } finally {
                                        setBusy(false);
                                    }
                                }}
                            />
                        )}
                    </Field>
                    <Field label={t("JSON data")} error={error}>
                        {(id) => (
                            <Textarea
                                id={id}
                                className="min-h-64 font-mono"
                                value={json}
                                onChange={(event) =>
                                    setJson(event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Button type="submit" className="mt-4" loading={busy}>
                        {t("Import")}
                    </Button>
                </form>
            </Dialog>
        </AdminLayout>
    );
}
