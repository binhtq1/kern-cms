import { pageSizeOptions } from "@admin/lib/pagination";
import { useAdminTranslation } from "@admin/lib/translate";
import { Button } from "./ui/button";
import { Select } from "./ui/select";
export function TablePagination({
    total,
    previous,
    next,
    currentPage,
    lastPage,
    perPage = 20,
    onPageSize,
    onVisit,
    loading = false,
}: {
    total: number;
    previous?: string | null;
    next?: string | null;
    currentPage?: number;
    lastPage?: number;
    perPage?: number;
    onPageSize?: (size: number) => void;
    onVisit: (url: string) => void;
    loading?: boolean;
}) {
    const t = useAdminTranslation();
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm">
            <div className="flex flex-wrap items-center gap-3 text-muted-foreground">
                <span>{t(":count results", { count: total })}</span>
                {onPageSize && (
                    <Select
                        className="w-24"
                        aria-label={t("Rows per page")}
                        value={perPage}
                        disabled={loading}
                        onValueChange={(value) => onPageSize(Number(value))}
                    >
                        {pageSizeOptions(perPage).map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </Select>
                )}
            </div>
            {(previous || next) && (
                <nav
                    aria-label={t("Pagination")}
                    className="flex items-center gap-2"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={loading || !previous}
                        onClick={() => previous && onVisit(previous)}
                    >
                        {t("Previous")}
                    </Button>
                    {currentPage !== undefined && (
                        <span className="tabular-nums" aria-live="polite">
                            {currentPage}
                            {lastPage ? ` / ${lastPage}` : ""}
                        </span>
                    )}
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={loading || !next}
                        onClick={() => next && onVisit(next)}
                    >
                        {t("Next")}
                    </Button>
                </nav>
            )}
        </div>
    );
}
