import { DataTableToolbar } from "@admin/components/data-table-toolbar";
import { type RegisteredFilter } from "@admin/components/registered-table-controls";
import { SearchInput } from "@admin/components/search-input";
import type { TableVisibility } from "@admin/components/table-tools";
import { Button } from "@admin/components/ui/button";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";

import type { Dispatch, SetStateAction } from "react";
export function AclFilters({
    visit,
    setSearch,
    search,
    kind,
    filters,
    setShowFilters,
    showFilters,
    setAdvanced,
    tableTools,
    loading = false,
}: {
    loading?: boolean;
    visit: (next: Record<string, string | number | RegisteredFilter[]>) => void;
    setSearch: Dispatch<SetStateAction<string>>;
    search: string;
    kind: string;
    filters: {
        advanced?: RegisteredFilter[] | undefined;
        status?: string | undefined;
        sort?: string | undefined;
        direction?: string | undefined;
        per_page?: number | undefined;
    };
    setShowFilters: Dispatch<SetStateAction<boolean>>;
    showFilters: boolean;
    setAdvanced: Dispatch<SetStateAction<RegisteredFilter[]>>;
    tableTools: {
        buttons: string[];
        columns: { key: string; label: string }[];
        visibility?: TableVisibility;
        onVisibilityChange: Dispatch<SetStateAction<Record<string, number>>>;
        indexUrl: string;
        filters: Record<string, unknown>;
    };
}) {
    const t = useAdminTranslation();
    return (
        <DataTableToolbar
            loading={loading}
            onSubmit={(event) => {
                event.preventDefault();
                visit({});
            }}
            tools={tableTools}
        >
            <SearchInput
                onClear={() => {
                    setSearch("");
                }}
                aria-label={t("Search")}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={t("Search")}
                className="max-w-sm"
            />
            <Button type="submit" variant="outline" disabled={loading}>
                {t("Search")}
            </Button>
            {kind === "users" && (
                <Select
                    disabled={loading}
                    aria-label={t("Status")}
                    value={filters.status ?? ""}
                    onValueChange={(value) => visit({ status: value })}
                    className="w-44"
                >
                    <option value="">{t("All statuses")}</option>
                    <option value="activated">{t("Activated")}</option>
                    <option value="deactivated">{t("Deactivated")}</option>
                </Select>
            )}
            <Button
                variant="outline"
                onClick={() => setShowFilters(!showFilters)}
                aria-expanded={showFilters}
            >
                {t("Advanced filters")}
            </Button>
            {Boolean(search || filters.status || filters.advanced?.length) && (
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
    );
}
