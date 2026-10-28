import { router } from "@inertiajs/react";
import { Check, ImageOff, LayoutTemplate, SearchX } from "lucide-react";
import { useRef, useState } from "react";
import { SearchInput } from "@admin/components/search-input";
import { Badge } from "@admin/components/ui/badge";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";

type Theme = {
    slug: string;
    name: string;
    description: string;
    version: string;
    author: string;
    inherit: string | null;
    screenshot: string;
    active: boolean;
};

function ThemePreview({
    theme,
    className,
}: {
    theme: Theme;
    className?: string;
}) {
    const t = useAdminTranslation();
    const [failedSource, setFailedSource] = useState<string | null>(null);
    const hasPreview =
        !!theme.screenshot &&
        failedSource !== theme.screenshot &&
        !/\/vendor\/core\/core\/base\/images\/placeholder\.png(?:\?|$)/.test(
            theme.screenshot,
        );

    return (
        <div
            className={cn(
                "relative flex aspect-[16/10] items-center justify-center overflow-hidden bg-muted/60",
                theme.active && !hasPreview && "max-md:aspect-[5/2]",
                className,
            )}
        >
            {hasPreview ? (
                <img
                    src={theme.screenshot}
                    alt=""
                    className="size-full object-cover object-top"
                    loading={theme.active ? "eager" : "lazy"}
                    onError={() => setFailedSource(theme.screenshot)}
                />
            ) : (
                <div className="flex flex-col items-center gap-3 px-5 text-center text-muted-foreground">
                    <div className="flex size-14 items-center justify-center rounded-2xl border border-border/80 bg-card shadow-sm">
                        <ImageOff aria-hidden="true" className="size-6" />
                    </div>
                    <span className="text-xs font-medium">
                        {t("No screenshot available")}
                    </span>
                </div>
            )}
        </div>
    );
}

function ThemeMetadata({ theme }: { theme: Theme }) {
    const t = useAdminTranslation();

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted-foreground">
            {theme.author && (
                <span className="break-words [overflow-wrap:anywhere]">
                    {t("Theme author")}: {theme.author}
                </span>
            )}
            {theme.version && (
                <span className="break-words [overflow-wrap:anywhere]">
                    {t("Version")}: {theme.version}
                </span>
            )}
            {theme.inherit && (
                <span className="break-words [overflow-wrap:anywhere]">
                    {t("Based on :name", { name: theme.inherit })}
                </span>
            )}
        </div>
    );
}

export default function Index({
    themes,
    urls,
    permissions,
}: {
    themes: Theme[];
    urls: { activate: string; remove: string };
    permissions: { activate: boolean; remove: boolean };
}) {
    const t = useAdminTranslation();
    const [query, setQuery] = useState("");
    const [target, setTarget] = useState<{
        theme: Theme;
        remove: boolean;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const cancelButton = useRef<HTMLButtonElement>(null);
    const searchInput = useRef<HTMLInputElement>(null);
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const visibleThemes = themes.filter((theme) =>
        [theme.name, theme.slug, theme.author, theme.description, theme.version]
            .join(" ")
            .toLocaleLowerCase()
            .includes(normalizedQuery),
    );
    const activeTheme = visibleThemes.find((theme) => theme.active);
    const otherThemes = visibleThemes.filter((theme) => !theme.active);

    return (
        <AdminLayout title={t("Themes")}>
            <div className="space-y-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                "Choose and manage the appearance of your website.",
                            )}
                        </p>
                        <p
                            role="status"
                            className="text-xs text-muted-foreground"
                        >
                            {t(
                                visibleThemes.length === 1
                                    ? ":count theme"
                                    : ":count themes",
                                { count: visibleThemes.length },
                            )}
                        </p>
                    </div>
                    <SearchInput
                        ref={searchInput}
                        aria-label={t("Search themes")}
                        placeholder={t("Search themes")}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onClear={() => setQuery("")}
                        className="w-full sm:max-w-xs"
                    />
                </div>

                {activeTheme && (
                    <section
                        aria-labelledby="current-theme-heading"
                        className="space-y-3"
                    >
                        <h2
                            id="current-theme-heading"
                            className="text-sm font-semibold"
                        >
                            {t("Current theme")}
                        </h2>
                        <Card className="overflow-hidden border-primary/25 shadow-sm">
                            <div className="flex flex-col md:flex-row">
                                <ThemePreview
                                    theme={activeTheme}
                                    className="w-full shrink-0 border-b md:w-72 md:border-b-0 md:border-e lg:w-80"
                                />
                                <div className="flex min-w-0 flex-1 flex-col justify-center gap-4 p-5 sm:p-6">
                                    <div className="space-y-2">
                                        <Badge
                                            variant="success"
                                            className="w-fit"
                                        >
                                            <Check
                                                aria-hidden="true"
                                                className="size-3"
                                            />
                                            {t("Active")}
                                        </Badge>
                                        <h3 className="break-words text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">
                                            {activeTheme.name}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                "Currently live on your website",
                                            )}
                                        </p>
                                    </div>
                                    {activeTheme.description && (
                                        <p className="max-w-2xl break-words text-sm leading-relaxed text-muted-foreground [overflow-wrap:anywhere]">
                                            {activeTheme.description}
                                        </p>
                                    )}
                                    <ThemeMetadata theme={activeTheme} />
                                </div>
                            </div>
                        </Card>
                    </section>
                )}

                {otherThemes.length > 0 && (
                    <section
                        aria-labelledby="other-themes-heading"
                        className="space-y-3"
                    >
                        <div className="flex items-center gap-2">
                            <h2
                                id="other-themes-heading"
                                className="text-sm font-semibold"
                            >
                                {t("Other themes")}
                            </h2>
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                {otherThemes.length}
                            </span>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {otherThemes.map((theme) => (
                                <Card
                                    key={theme.slug}
                                    className="flex flex-col overflow-hidden shadow-sm"
                                >
                                    <ThemePreview
                                        theme={theme}
                                        className="border-b"
                                    />
                                    <div className="flex min-w-0 flex-1 flex-col gap-4 p-5">
                                        <div className="min-w-0 flex-1 space-y-2">
                                            <h3 className="break-words text-base font-semibold [overflow-wrap:anywhere]">
                                                {theme.name}
                                            </h3>
                                            {theme.description && (
                                                <p className="break-words text-sm leading-relaxed text-muted-foreground [overflow-wrap:anywhere]">
                                                    {theme.description}
                                                </p>
                                            )}
                                        </div>
                                        <ThemeMetadata theme={theme} />
                                        {(permissions.activate ||
                                            permissions.remove) && (
                                            <div className="flex flex-wrap gap-2 border-t pt-4">
                                                {permissions.activate && (
                                                    <Button
                                                        onClick={() =>
                                                            setTarget({
                                                                theme,
                                                                remove: false,
                                                            })
                                                        }
                                                    >
                                                        {t("Activate")}
                                                    </Button>
                                                )}
                                                {permissions.remove && (
                                                    <Button
                                                        variant="outline"
                                                        onClick={() =>
                                                            setTarget({
                                                                theme,
                                                                remove: true,
                                                            })
                                                        }
                                                    >
                                                        {t("Remove")}
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </Card>
                            ))}
                        </div>
                    </section>
                )}

                {visibleThemes.length === 0 && (
                    <Card className="flex flex-col items-center gap-3 px-5 py-12 text-center">
                        {normalizedQuery ? (
                            <SearchX
                                aria-hidden="true"
                                className="size-8 text-muted-foreground"
                            />
                        ) : (
                            <LayoutTemplate
                                aria-hidden="true"
                                className="size-8 text-muted-foreground"
                            />
                        )}
                        <p className="text-sm text-muted-foreground">
                            {t(
                                normalizedQuery
                                    ? "No results found"
                                    : "No themes installed",
                            )}
                        </p>
                        {normalizedQuery && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setQuery("");
                                    searchInput.current?.focus();
                                }}
                            >
                                {t("Clear search")}
                            </Button>
                        )}
                    </Card>
                )}
                {!normalizedQuery &&
                    activeTheme &&
                    otherThemes.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t("No other themes installed")}
                        </p>
                    )}
            </div>
            <Dialog
                open={!!target}
                onOpenChange={(open) => !open && !busy && setTarget(null)}
                title={t(target?.remove ? "Remove theme" : "Activate theme")}
                description={target?.theme.name}
                size="sm"
                initialFocusRef={cancelButton}
                closeDisabled={busy}
                footer={
                    <>
                        <Button
                            ref={cancelButton}
                            variant="outline"
                            disabled={busy}
                            onClick={() => setTarget(null)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            variant={target?.remove ? "destructive" : "default"}
                            loading={busy}
                            onClick={() => {
                                if (!target) return;
                                setBusy(true);
                                router.post(
                                    target.remove ? urls.remove : urls.activate,
                                    { theme: target.theme.slug },
                                    {
                                        onSuccess: () => setTarget(null),
                                        onFinish: () => setBusy(false),
                                    },
                                );
                            }}
                        >
                            {t(target?.remove ? "Remove" : "Activate")}
                        </Button>
                    </>
                }
            >
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {t(
                        target?.remove
                            ? "This action cannot be undone."
                            : "This changes the public website theme.",
                    )}
                </p>
            </Dialog>
        </AdminLayout>
    );
}
