import { SearchInput } from "@admin/components/search-input";
import { normalizeSearch } from "@admin/lib/utils";
import { Head } from "@inertiajs/react";
import { useRef, useState } from "react";
import {
    Archive,
    Blocks,
    ChevronRight,
    Clock,
    Database,
    FileWarning,
    History,
    Info,
    KeyRound,
    Recycle,
    Search,
    ServerCog,
    ShieldCheck,
    Users,
} from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";

import { useAdminTranslation } from "@admin/lib/translate";

type SystemItem = { title: string; description: string; url: string };

function systemIcon(url: string) {
    const path = new URL(url, window.location.origin).pathname;
    if (/roles|permissions/.test(path)) return ShieldCheck;
    if (/users/.test(path)) return Users;
    if (/backup/.test(path)) return Archive;
    if (/audit/.test(path)) return History;
    if (/request-log/.test(path)) return FileWarning;
    if (/cache/.test(path)) return Database;
    if (/cleanup/.test(path)) return Recycle;
    if (/cron/.test(path)) return Clock;
    if (/info/.test(path)) return Info;
    if (/token|api/.test(path)) return KeyRound;
    if (/plugin/.test(path)) return Blocks;
    return ServerCog;
}

export default function SystemIndex({
    title,
    sections,
}: {
    title: string;
    sections: { items: SystemItem[] }[];
}) {
    const t = useAdminTranslation();
    const [search, setSearch] = useState("");
    const searchInput = useRef<HTMLInputElement>(null);
    const query = normalizeSearch(search);
    const items = sections.flatMap((section) => section.items);
    const visible = items.filter((item) =>
        normalizeSearch(`${item.title} ${item.description}`).includes(query),
    );

    function clearSearch() {
        setSearch("");
        searchInput.current?.focus();
    }

    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-muted-foreground">
                    {t(
                        "Manage access, monitor activity and maintain your system.",
                    )}
                </p>
                <SearchInput
                    className="w-full sm:w-80"
                    ref={searchInput}
                    aria-label={t("Search system tools")}
                    placeholder={t("Search system tools")}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onClear={clearSearch}
                    onKeyDown={(event) => {
                        if (event.key === "Escape") setSearch("");
                    }}
                />
            </div>
            {visible.length > 0 ? (
                <Card className="overflow-hidden">
                    <nav aria-label={title}>
                        <ul className="-mr-px -mb-px grid sm:grid-cols-2 xl:grid-cols-3">
                            {visible.map((item) => {
                                const Icon = systemIcon(item.url);
                                return (
                                    <li
                                        key={item.url}
                                        className="flex min-w-0 border-r border-b"
                                    >
                                        <a
                                            href={item.url}
                                            className="group flex w-full min-w-0 items-start gap-3 p-5 outline-none transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted/30 text-primary">
                                                <Icon
                                                    size={20}
                                                    strokeWidth={1.5}
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <h2 className="text-sm font-semibold group-hover:text-primary">
                                                    {item.title}
                                                </h2>
                                                {item.description && (
                                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                )}
                                            </div>
                                            <ChevronRight
                                                size={16}
                                                aria-hidden="true"
                                                className="mt-1 shrink-0 text-muted-foreground group-hover:text-primary"
                                            />
                                        </a>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>
                </Card>
            ) : (
                <Card className="flex flex-col items-center gap-3 p-10 text-center sm:p-12">
                    <Search
                        size={24}
                        strokeWidth={1.5}
                        aria-hidden="true"
                        className="text-muted-foreground"
                    />
                    <p role="status" className="text-sm text-muted-foreground">
                        {t(
                            query
                                ? "No system tools found"
                                : "No system tools available",
                        )}
                    </p>
                    {query && (
                        <Button variant="outline" onClick={clearSearch}>
                            {t("Clear search")}
                        </Button>
                    )}
                </Card>
            )}
        </AdminLayout>
    );
}
