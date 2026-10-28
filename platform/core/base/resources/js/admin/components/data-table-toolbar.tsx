import type { ComponentProps, ReactNode } from "react";
import { Spinner } from "./loading-indicator";
import { useAdminTranslation } from "@admin/lib/translate";
import { FilterToolbar } from "./filter-toolbar";
import { TableTools, type TableVisibility } from "./table-tools";

type TableColumn = { key: string; label: string };

type TableToolsProps = {
    buttons: string[];
    columns: TableColumn[];
    visibility?: TableVisibility;
    onVisibilityChange: (columns: Record<string, number>) => void;
    indexUrl: string;
    filters: Record<string, unknown>;
    hasExport?: boolean;
};

type DataTableToolbarProps = Omit<
    ComponentProps<typeof FilterToolbar>,
    "children"
> & {
    children?: ReactNode;
    tools?: TableToolsProps;
    tableActions?: ReactNode;
    loading?: boolean;
};

/**
 * Shared filter and action row for every paginated admin table.
 *
 * Keeping the table utilities in the same semantic form makes search, export,
 * column visibility, and reload behave and appear consistently across modules.
 */
export function DataTableToolbar({
    children,
    tools,
    tableActions,
    loading = false,
    ...props
}: DataTableToolbarProps) {
    const t = useAdminTranslation();
    return (
        <FilterToolbar {...props} aria-busy={loading}>
            <fieldset className="contents" disabled={loading}>
                {children}
            </fieldset>
            <span
                role="status"
                className="inline-flex w-5 shrink-0 items-center"
            >
                {loading && (
                    <>
                        <Spinner />
                        <span className="sr-only">{t("Loading...")}</span>
                    </>
                )}
            </span>
            {(tools || tableActions) && (
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    {tools && <TableTools {...tools} disabled={loading} />}
                    {tableActions}
                </div>
            )}
        </FilterToolbar>
    );
}
