import { Link } from "@inertiajs/react";
import {
    ArrowDown,
    ArrowUp,
    CornerDownLeft,
    Hash,
    Search,
    X,
} from "lucide-react";
import { useEffect, useId, useRef, useState } from "react";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";
import { Input } from "./ui/input";
import { LoadingIndicator } from "./loading-indicator";

type SearchResult = {
    title: string;
    description: string;
    parents: string[];
    url: string;
    inertia: boolean;
    shouldOpenNewTab: boolean;
};

export function GlobalSearch({
    url,
    onNavigate,
}: {
    url: string;
    onNavigate: () => void;
}) {
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState("");
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [retry, setRetry] = useState(0);
    const [selected, setSelected] = useState(0);
    const input = useRef<HTMLInputElement>(null);
    const list = useRef<HTMLDivElement>(null);
    const listId = useId();

    function changeOpen(value: boolean) {
        setOpen(value);
        if (!value) {
            setKeyword("");
            setResults([]);
            setLoading(false);
            setFailed(false);
        }
    }

    useEffect(() => {
        const handleShortcut = (event: KeyboardEvent) => {
            if (open || event.defaultPrevented || event.isComposing) return;
            if (
                ((event.metaKey || event.ctrlKey) &&
                    ["k", "/"].includes(event.key.toLowerCase())) ||
                (event.key === "/" && event.target === document.body)
            ) {
                event.preventDefault();
                setOpen(true);
            }
        };
        window.addEventListener("keydown", handleShortcut);
        return () => window.removeEventListener("keydown", handleShortcut);
    }, [open]);

    useEffect(() => {
        if (!open || !keyword.trim()) return;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const endpoint = new URL(url, window.location.origin);
                endpoint.searchParams.set("keyword", keyword);
                endpoint.searchParams.set("admin_json", "1");
                const response = await fetch(endpoint, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error("Search failed");
                const payload = await response.json();
                if (payload.error || !Array.isArray(payload.data))
                    throw new Error("Invalid search response");
                if (controller.signal.aborted) return;
                setResults(
                    payload.data.filter((result: SearchResult) => {
                        try {
                            return ["http:", "https:"].includes(
                                new URL(result.url, window.location.origin)
                                    .protocol,
                            );
                        } catch {
                            return false;
                        }
                    }),
                );
                setSelected(0);
            } catch {
                if (!controller.signal.aborted) setFailed(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 200);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, keyword, url, retry]);

    useEffect(() => {
        list.current
            ?.querySelector<HTMLElement>(`[data-result-index="${selected}"]`)
            ?.scrollIntoView({ block: "nearest" });
    }, [selected, results]);

    return (
        <>
            <Button
                variant="ghost"
                className="px-2 text-muted-foreground sm:px-3"
                onClick={() => changeOpen(true)}
                aria-label={t("Search")}
                aria-keyshortcuts="Control+K Meta+K Control+/ Meta+/ /"
            >
                <Search aria-hidden="true" />
                <span className="hidden lg:inline">{t("Search")}</span>
                <kbd className="hidden rounded border px-1.5 text-[10px] font-normal lg:inline">
                    ⌘ / Ctrl K
                </kbd>
            </Button>
            <Dialog
                open={open}
                onOpenChange={changeOpen}
                title={t("Search")}
                initialFocusRef={input}
            >
                <div
                    onKeyDown={(event) => {
                        if (
                            event.nativeEvent.isComposing ||
                            loading ||
                            failed ||
                            !results.length
                        )
                            return;
                        if (["ArrowDown", "ArrowUp"].includes(event.key)) {
                            event.preventDefault();
                            setSelected(
                                (index) =>
                                    (index +
                                        (event.key === "ArrowDown" ? 1 : -1) +
                                        results.length) %
                                    results.length,
                            );
                            input.current?.focus();
                        } else if (
                            event.key === "Enter" &&
                            event.target === input.current
                        ) {
                            event.preventDefault();
                            list.current
                                ?.querySelector<HTMLAnchorElement>(
                                    `[data-result-index="${selected}"]`,
                                )
                                ?.click();
                        }
                    }}
                >
                    <div className="relative">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            ref={input}
                            role="combobox"
                            aria-label={t("Search")}
                            aria-autocomplete="list"
                            aria-expanded={results.length > 0}
                            aria-controls={listId}
                            aria-activedescendant={
                                results.length
                                    ? `${listId}-${selected}`
                                    : undefined
                            }
                            placeholder={t("Search…")}
                            autoComplete="off"
                            maxLength={1024}
                            className="ps-9 pe-10"
                            value={keyword}
                            onChange={(event) => {
                                setKeyword(event.target.value);
                                setResults([]);
                                setFailed(false);
                                setLoading(Boolean(event.target.value.trim()));
                            }}
                        />
                        {keyword && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="absolute end-1 top-1/2 size-8 -translate-y-1/2"
                                aria-label={t("Clear search")}
                                onClick={() => {
                                    setKeyword("");
                                    setResults([]);
                                    setFailed(false);
                                    setLoading(false);
                                    input.current?.focus();
                                }}
                            >
                                <X aria-hidden="true" />
                            </Button>
                        )}
                    </div>
                    <div
                        ref={list}
                        id={listId}
                        role="listbox"
                        aria-label={t("Search results")}
                        aria-busy={loading}
                        className="mt-3 max-h-[min(50dvh,24rem)] overflow-y-auto"
                    >
                        {results.map((result, index) => {
                            const props = {
                                id: `${listId}-${index}`,
                                role: "option",
                                "aria-selected": index === selected,
                                "data-result-index": index,
                                className: cn(
                                    "flex items-start gap-3 rounded-md px-3 py-3 text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring",
                                    index === selected && "bg-accent",
                                ),
                                onFocus: () => setSelected(index),
                                onClick: () => {
                                    changeOpen(false);
                                    onNavigate();
                                },
                            };
                            const content = (
                                <>
                                    <Hash
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0 text-primary-ink"
                                    />
                                    <span className="min-w-0 break-words">
                                        <span className="font-medium">
                                            {result.parents.length > 0 && (
                                                <span className="text-muted-foreground">
                                                    {result.parents.join(" › ")}{" "}
                                                    ›{" "}
                                                </span>
                                            )}
                                            {result.title}
                                        </span>
                                        {result.description && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {result.description}
                                            </span>
                                        )}
                                    </span>
                                </>
                            );
                            return result.inertia &&
                                !result.shouldOpenNewTab ? (
                                <Link
                                    key={`${result.url}-${index}`}
                                    href={result.url}
                                    {...props}
                                >
                                    {content}
                                </Link>
                            ) : (
                                <a
                                    key={`${result.url}-${index}`}
                                    href={result.url}
                                    target={
                                        result.shouldOpenNewTab
                                            ? "_blank"
                                            : undefined
                                    }
                                    rel={
                                        result.shouldOpenNewTab
                                            ? "noopener noreferrer"
                                            : undefined
                                    }
                                    {...props}
                                >
                                    {content}
                                </a>
                            );
                        })}
                    </div>
                    <div
                        role="status"
                        aria-live="polite"
                        className="text-sm text-muted-foreground"
                    >
                        {loading ? (
                            <LoadingIndicator
                                label={t("Searching…")}
                                className="flex py-8"
                                announce={false}
                            />
                        ) : failed ? (
                            <div className="space-y-3 py-6 text-center">
                                <p>
                                    {t("Unable to search. Please try again.")}
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setFailed(false);
                                        setLoading(true);
                                        setRetry((value) => value + 1);
                                    }}
                                >
                                    {t("Try again")}
                                </Button>
                            </div>
                        ) : (
                            !results.length && (
                                <p className="py-8 text-center">
                                    {t("No results found")}
                                </p>
                            )
                        )}
                    </div>
                    <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t pt-4 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <kbd className="rounded border p-1">
                                <CornerDownLeft
                                    aria-hidden="true"
                                    className="size-3"
                                />
                            </kbd>
                            {t("to select")}
                        </span>
                        <span className="flex items-center gap-1.5">
                            <kbd className="rounded border p-1">
                                <ArrowUp
                                    aria-hidden="true"
                                    className="size-3"
                                />
                            </kbd>
                            <kbd className="rounded border p-1">
                                <ArrowDown
                                    aria-hidden="true"
                                    className="size-3"
                                />
                            </kbd>
                            {t("to navigate")}
                        </span>
                        <span className="flex items-center gap-1.5">
                            <kbd className="rounded border px-1.5 py-1">
                                esc
                            </kbd>
                            {t("to close")}
                        </span>
                    </div>
                </div>
            </Dialog>
        </>
    );
}
