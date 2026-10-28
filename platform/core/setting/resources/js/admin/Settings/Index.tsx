import { SearchInput } from "@admin/components/search-input";

import { normalizeSearch } from "@admin/lib/utils";
import { Head } from "@inertiajs/react";
import { useRef, useState } from "react";
import {
    Settings,
    Mail,
    Image,
    Globe,
    ShieldCheck,
    Plug,
    Search,
    ArrowUpRight,
    Database,
    Gauge,
    Palette,
} from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Card } from "@admin/components/ui/card";

import { useAdminTranslation } from "@admin/lib/translate";
function settingIcon(url: string) {
    const path = new URL(url, window.location.origin).pathname;
    if (/email/.test(path)) return Mail;
    if (/media/.test(path)) return Image;
    if (/appearance/.test(path)) return Palette;
    if (/language|locale|translation|permalink/.test(path)) return Globe;
    if (/captcha|rules/.test(path)) return ShieldCheck;
    if (/api|social/.test(path)) return Plug;
    if (/cache/.test(path)) return Database;
    if (/optimize|analytics/.test(path)) return Gauge;
    return Settings;
}
export default function SettingsIndex({
    title,
    sections,
}: {
    title: string;
    sections: {
        title: string;
        description: string;
        items: { title: string; description: string; url: string }[];
    }[];
}) {
    const t = useAdminTranslation();
    const [search, setSearch] = useState("");
    const searchInput = useRef<HTMLInputElement>(null);
    const visible = sections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) =>
                normalizeSearch(`${item.title} ${item.description}`).includes(
                    normalizeSearch(search),
                ),
            ),
        }))
        .filter((section) => section.items.length);
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-muted-foreground">
                    {t("Manage your website and administration settings.")}
                </p>
                <SearchInput
                    className="w-full sm:w-80"
                    ref={searchInput}
                    aria-label={t("Search settings")}
                    placeholder={t("Search settings")}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onClear={() => setSearch("")}
                    onKeyDown={(event) => {
                        if (event.key === "Escape") setSearch("");
                    }}
                />
            </div>
            <div className="space-y-8">
                {visible.map((section) => (
                    <section
                        key={section.title}
                        className="grid items-start gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]"
                    >
                        <div className="space-y-2">
                            <h2 className="text-base font-semibold">
                                {section.title}
                            </h2>
                            {section.description && (
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {section.description}
                                </p>
                            )}
                        </div>
                        <Card className="grid overflow-hidden sm:grid-cols-2">
                            {section.items.map((item) => {
                                const Icon = settingIcon(item.url);
                                return (
                                    <a
                                        href={item.url}
                                        key={item.url}
                                        className="group flex min-w-0 items-start gap-3 border-b p-5 outline-none transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring sm:odd:border-r"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted/30 text-primary">
                                            <Icon size={20} strokeWidth={1.5} />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <h3 className="text-sm font-semibold group-hover:text-primary">
                                                {item.title}
                                            </h3>
                                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                {item.description}
                                            </p>
                                        </div>
                                        <ArrowUpRight
                                            size={15}
                                            className="mt-1 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100"
                                        />
                                    </a>
                                );
                            })}
                        </Card>
                    </section>
                ))}
                {!visible.length && (
                    <p className="rounded-md border bg-card p-12 text-center text-sm text-muted-foreground">
                        {t("No settings found")}
                    </p>
                )}
            </div>
        </AdminLayout>
    );
}
