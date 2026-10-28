import { Link, usePage } from "@inertiajs/react";
import { cn } from "@admin/lib/utils";
import { useAdminTranslation } from "@admin/lib/translate";

export type EmailNavigation = { label: string; url: string }[];
export function SettingsSectionNav({
    links = [],
}: {
    links?: EmailNavigation;
}) {
    const t = useAdminTranslation();
    const { url } = usePage();
    const current = new URL(url, window.location.origin).pathname.replace(
        /\/$/,
        "",
    );
    const active = [...links]
        .sort((a, b) => b.url.length - a.url.length)
        .find((item) => {
            const path = new URL(
                item.url,
                window.location.origin,
            ).pathname.replace(/\/$/, "");
            return current === path || current.startsWith(`${path}/`);
        });
    if (!links.length) return null;
    return (
        <nav
            aria-label={t("Email settings")}
            className="flex flex-wrap gap-1 lg:flex-col"
        >
            {links.map((item) => (
                <Link
                    key={item.url}
                    href={item.url}
                    aria-current={item === active ? "page" : undefined}
                    className={cn(
                        "rounded-md px-3 py-2 text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring",
                        item === active
                            ? "bg-secondary font-medium text-foreground"
                            : "text-muted-foreground hover:bg-accent hover:text-foreground",
                    )}
                >
                    {t(item.label)}
                </Link>
            ))}
        </nav>
    );
}
