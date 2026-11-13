import { Button } from "@admin/components/ui/button";
import { useAdminTranslation } from "@admin/lib/translate";
import { Link } from "@inertiajs/react";

type TranslationSection = "admin" | "theme" | "locales";

export function TranslationNavigation({
    active,
    urls,
}: {
    active: TranslationSection;
    urls: { admin: string; theme: string; locales: string };
}) {
    const t = useAdminTranslation();
    const sections: {
        key: TranslationSection;
        label: string;
        href: string;
    }[] = [
        { key: "admin", label: t("Admin translations"), href: urls.admin },
        { key: "theme", label: t("Theme translations"), href: urls.theme },
        { key: "locales", label: t("Locales"), href: urls.locales },
    ];

    return (
        <nav
            className="mb-5 flex flex-wrap gap-2"
            aria-label={t("Translations")}
        >
            {sections.map((section) => (
                <Button
                    key={section.key}
                    asChild
                    variant={active === section.key ? "default" : "outline"}
                >
                    <Link
                        href={section.href}
                        aria-current={
                            active === section.key ? "page" : undefined
                        }
                    >
                        {section.label}
                    </Link>
                </Button>
            ))}
        </nav>
    );
}
