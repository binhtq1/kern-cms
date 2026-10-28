import { SearchInput } from "@admin/components/search-input";
import { Button } from "@admin/components/ui/button";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { Grid2X2, List, RefreshCw } from "lucide-react";
import type { Dispatch, SetStateAction } from "react";
export function MediaToolbar({
    view,
    setView,
    setFolder,
    setPage,
    setSearch,
    search,
    filter,
    setFilter,
    sort,
    setSort,
    setVersion,
    loading,
    grid,
    setGrid,
}: {
    view: string;
    setView: Dispatch<SetStateAction<string>>;
    setFolder: Dispatch<SetStateAction<string | number>>;
    setPage: Dispatch<SetStateAction<number>>;
    setSearch: Dispatch<SetStateAction<string>>;
    search: string;
    filter: string;
    setFilter: Dispatch<SetStateAction<string>>;
    sort: string;
    setSort: Dispatch<SetStateAction<string>>;
    setVersion: Dispatch<SetStateAction<number>>;
    loading: boolean;
    grid: boolean;
    setGrid: Dispatch<SetStateAction<boolean>>;
}) {
    const t = useAdminTranslation();
    return (
        <div className="flex flex-wrap items-center gap-3 border-b p-3">
            <Select
                containerClassName="lg:hidden"
                aria-label={t("View")}
                value={view}
                onValueChange={(value) => {
                    setView(value);
                    setFolder(0);
                    setPage(1);
                }}
            >
                <option value="all_media">{t("All media")}</option>
                <option value="recent">{t("Recent")}</option>
                <option value="favorites">{t("Favorites")}</option>
                <option value="trash">{t("Trash")}</option>
            </Select>
            <SearchInput
                onClear={() => {
                    setSearch("");
                    setPage(1);
                }}
                aria-label={t("Search media")}
                placeholder={t("Search media")}
                value={search}
                onChange={(event) => {
                    setSearch(event.target.value);
                    setPage(1);
                }}
                className="min-w-40 flex-1"
            />
            <Select
                containerClassName="lg:hidden"
                aria-label={t("File type")}
                value={filter}
                onValueChange={(value) => {
                    setFilter(value);
                    setPage(1);
                }}
            >
                <option value="everything">{t("All files")}</option>
                <option value="image">{t("Images")}</option>
                <option value="document">{t("Documents")}</option>
                <option value="video">{t("Video")}</option>
                <option value="audio">{t("Audio")}</option>
            </Select>
            <Select
                aria-label={t("Sort")}
                value={sort}
                onValueChange={(value) => setSort(value)}
            >
                <option value="name-asc">{t("Name A–Z")}</option>
                <option value="created_at-desc">{t("Newest first")}</option>
                <option value="name-desc">{t("Name Z–A")}</option>
                <option value="created_at-asc">{t("Oldest first")}</option>
                <option value="size-asc">{t("Smallest first")}</option>
                <option value="size-desc">{t("Largest first")}</option>
            </Select>
            <Button
                size="icon"
                variant="ghost"
                aria-label={t("Refresh")}
                loading={loading}
                onClick={() => setVersion((value) => value + 1)}
            >
                {!loading && <RefreshCw aria-hidden="true" />}
            </Button>
            <Button
                size="icon"
                variant="ghost"
                aria-label={t("Grid view")}
                aria-pressed={grid}
                onClick={() => setGrid(true)}
            >
                <Grid2X2 />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                aria-label={t("List view")}
                aria-pressed={!grid}
                onClick={() => setGrid(false)}
            >
                <List />
            </Button>
        </div>
    );
}
