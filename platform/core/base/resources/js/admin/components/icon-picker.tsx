import { SearchInput } from "@admin/components/search-input";
import { LoadingIndicator } from "./loading-indicator";
import { useEffect, useState } from "react";
import axios from "axios";
import { cleanIcon } from "@admin/lib/icon";
import { Shapes } from "lucide-react";
import { useAdminTranslation } from "@admin/lib/translate";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";
export function IconPicker({
    id,
    value,
    onChange,
    iconsUrl,
    disabled,
    readOnly,
    placeholder,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    iconsUrl?: string | null;
    disabled?: boolean;
    readOnly?: boolean;
    placeholder?: string;
}) {
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [icons, setIcons] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        if (!open || !iconsUrl) return;
        const abort = new AbortController();
        setLoading(true);
        setError("");
        setIcons({});
        const timer = setTimeout(() => {
            void axios
                .get(iconsUrl, {
                    params: { q: search.trim(), page, per_page: 100 },
                    signal: abort.signal,
                })
                .then((response) => {
                    if (response.data.error)
                        throw new Error(response.data.message);
                    setIcons(response.data.data.data);
                })
                .catch((failure) => {
                    if (!abort.signal.aborted) setError(failure.message);
                })
                .finally(() => {
                    if (!abort.signal.aborted) setLoading(false);
                });
        }, 200);
        return () => {
            clearTimeout(timer);
            abort.abort();
        };
    }, [open, iconsUrl, search, page]);
    return (
        <div className="min-w-0">
            <div className="flex gap-2">
                <Input
                    id={id}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    disabled={disabled}
                    readOnly={readOnly}
                    placeholder={placeholder}
                />
                <Button
                    variant="outline"
                    disabled={disabled || readOnly || !iconsUrl}
                    aria-label={t("Choose icon")}
                    onClick={() => {
                        setPage(1);
                        setSearch("");
                        setOpen(true);
                    }}
                >
                    <Shapes aria-hidden="true" />
                    {t("Choose")}
                </Button>
            </div>
            <Dialog open={open} onOpenChange={setOpen} title={t("Choose icon")}>
                <SearchInput
                    className="flex-1"
                    onClear={() => {
                        setSearch("");
                        setPage(1);
                    }}
                    autoFocus
                    aria-label={t("Search icons")}
                    placeholder={t("Search icons")}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setPage(1);
                    }}
                />
                {error && (
                    <p role="alert" className="my-3 text-sm text-destructive">
                        {error}
                    </p>
                )}
                <div
                    aria-busy={loading}
                    className="mt-4 grid max-h-80 grid-cols-2 gap-2 overflow-auto sm:grid-cols-4"
                >
                    {Object.entries(icons).map(([name, html]) => (
                        <Button
                            key={name}
                            variant={name === value ? "secondary" : "outline"}
                            className="h-auto min-h-20 flex-col p-2"
                            onClick={() => {
                                onChange(name);
                                setOpen(false);
                            }}
                        >
                            <span
                                aria-hidden="true"
                                className="[&_svg]:size-6"
                                dangerouslySetInnerHTML={{
                                    __html: cleanIcon(html),
                                }}
                            />
                            <span className="w-full truncate text-xs">
                                {name}
                            </span>
                        </Button>
                    ))}
                </div>
                {loading && (
                    <LoadingIndicator
                        label={t("Loading…")}
                        className="flex py-3"
                    />
                )}
                {!loading && !error && !Object.keys(icons).length && (
                    <p
                        role="status"
                        className="my-3 text-sm text-muted-foreground"
                    >
                        {t("No results found")}
                    </p>
                )}
                {(page > 1 || Object.keys(icons).length === 100) && (
                    <div className="mt-4 flex justify-end gap-2">
                        <Button
                            variant="outline"
                            disabled={page <= 1 || loading}
                            onClick={() => setPage(page - 1)}
                        >
                            {t("Previous")}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={
                                Object.keys(icons).length < 100 || loading
                            }
                            onClick={() => setPage(page + 1)}
                        >
                            {t("Next")}
                        </Button>
                    </div>
                )}
            </Dialog>
        </div>
    );
}
