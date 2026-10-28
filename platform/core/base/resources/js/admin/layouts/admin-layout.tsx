import { ConfirmHost } from "@admin/components/confirm-dialog";
import { GlobalSearch } from "@admin/components/global-search";
import { HeaderNotifications } from "@admin/components/header-notifications";
import { AppearanceBridge } from "@admin/components/admin-appearance";
import { activeNavigation } from "@admin/lib/navigation";
import { ToastHost } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";
import { Head, Link, usePage } from "@inertiajs/react";
import {
    FileText,
    LayoutDashboard,
    LogOut,
    Menu,
    ChevronRight,
    ChevronDown,
    FolderOpen,
    Image,
    Settings,
    Users,
    UserRound,
    X,
    Newspaper,
    Images,
    Contact,
    Palette,
    Puzzle,
    Wrench,
    Blocks,
    SlidersHorizontal,
    PanelLeftClose,
    PanelLeftOpen,
    ShieldCheck,
    type LucideIcon,
} from "lucide-react";
import { useEffect, useState, type ReactNode, type CSSProperties } from "react";
import type { SharedProps, NavigationItem } from "@admin/types";
import { initializeAdminSession } from "@admin/lib/admin-session";
import { ThemeSwitch } from "@admin/components/theme-switch";
import { cn } from "@admin/lib/utils";
import { Button } from "@admin/components/ui/button";
import {
    Sidebar,
    SidebarItem,
    SidebarGroup,
    SidebarScrollArea,
    SidebarRailGroup,
} from "@admin/components/ui/sidebar";
import {
    DropdownMenu,
    DropdownMenuItem,
} from "@admin/components/ui/dropdown-menu";

function navigationIcon(url?: string): LucideIcon {
    if (!url) {
        return FolderOpen;
    }
    const path = new URL(url, window.location.origin).pathname;
    if (/pages/.test(path)) {
        return FileText;
    }
    if (/galleries/.test(path)) {
        return Images;
    }
    if (/media/.test(path)) {
        return Image;
    }
    if (/blog/.test(path)) {
        return Newspaper;
    }
    if (/contacts/.test(path)) {
        return Contact;
    }
    if (/theme|menus|widgets/.test(path)) {
        return Palette;
    }
    if (/plugins/.test(path)) {
        return Puzzle;
    }
    if (/custom-fields/.test(path)) {
        return SlidersHorizontal;
    }
    if (/blocks/.test(path)) {
        return Blocks;
    }
    if (/users|roles|acl|members/.test(path)) {
        return Users;
    }
    if (/tools/.test(path)) {
        return Wrench;
    }
    if (/system/.test(path)) {
        return ShieldCheck;
    }
    if (/settings/.test(path)) {
        return Settings;
    }
    if (/dashboard|\/admin\/?$/.test(path)) {
        return LayoutDashboard;
    }
    return FolderOpen;
}
function containsActive(items: NavigationItem[]): boolean {
    return items.some(
        (item) => item.active || containsActive(item.children ?? []),
    );
}
function Navigation({
    items,
    nested = false,
    onNavigate,
    searching = false,
    showIcons = true,
}: {
    items: NavigationItem[];
    nested?: boolean;
    onNavigate: () => void;
    searching?: boolean;
    showIcons?: boolean;
}) {
    const t = useAdminTranslation();
    return (
        <ul
            className={cn(
                "space-y-0.5",
                nested && "ms-5 border-s border-border/80 ps-3",
            )}
        >
            {items.map((item, index) => {
                const Icon = navigationIcon(item.url);
                const content = (
                    <>
                        {!nested && showIcons && (
                            <Icon
                                aria-hidden="true"
                                className="size-[18px] shrink-0"
                            />
                        )}
                        <span className="min-w-0 flex-1 break-words">
                            {item.label}
                        </span>
                        {!!item.count && (
                            <>
                                <span
                                    aria-hidden="true"
                                    className="rounded-full bg-primary/10 px-1.5 text-xs font-medium tabular-nums text-primary-ink"
                                >
                                    {item.count}
                                </span>
                                <span className="sr-only">
                                    {item.count} {t("Unread")}
                                </span>
                            </>
                        )}
                    </>
                );
                if (!item.url && item.children?.length) {
                    return (
                        <li key={`${item.label}-${index}`}>
                            <SidebarGroup
                                label={item.label}
                                count={item.count}
                                active={
                                    item.active || containsActive(item.children)
                                }
                                icon={
                                    showIcons
                                        ? navigationIcon(item.children[0]?.url)
                                        : undefined
                                }
                                storageKey={`kern-admin-navigation:${item.children[0]?.url ?? item.label}`}
                                forceOpen={searching}
                            >
                                <Navigation
                                    items={item.children}
                                    nested
                                    searching={searching}
                                    showIcons={showIcons}
                                    onNavigate={onNavigate}
                                />
                            </SidebarGroup>
                        </li>
                    );
                }
                return (
                    <li key={`${item.label}-${index}`}>
                        {item.url ? (
                            <SidebarItem
                                asChild
                                active={item.active}
                                onClick={onNavigate}
                            >
                                {item.inertia ? (
                                    <Link href={item.url}>{content}</Link>
                                ) : (
                                    <a href={item.url}>{content}</a>
                                )}
                            </SidebarItem>
                        ) : (
                            <p className="px-3 pt-5 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                {item.label}
                            </p>
                        )}
                        {!!item.children?.length && (
                            <Navigation
                                items={item.children}
                                nested
                                searching={searching}
                                showIcons={showIcons}
                                onNavigate={onNavigate}
                            />
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
function RailMenuItems({
    items,
    onNavigate,
}: {
    items: NavigationItem[];
    onNavigate: () => void;
}) {
    return items.map((item, index) => (
        <div key={`${item.label}-${index}`}>
            {item.url ? (
                <DropdownMenuItem
                    asChild
                    className={cn(item.active && "bg-accent font-medium")}
                >
                    {item.inertia ? (
                        <Link
                            href={item.url}
                            aria-current={item.active ? "page" : undefined}
                            onClick={onNavigate}
                        >
                            {item.label}
                        </Link>
                    ) : (
                        <a
                            href={item.url}
                            aria-current={item.active ? "page" : undefined}
                            onClick={onNavigate}
                        >
                            {item.label}
                        </a>
                    )}
                </DropdownMenuItem>
            ) : (
                <p className="px-3 pb-1 pt-3 text-xs font-medium text-muted-foreground">
                    {item.label}
                </p>
            )}
            {!!item.children?.length && (
                <RailMenuItems items={item.children} onNavigate={onNavigate} />
            )}
        </div>
    ));
}

function CompactNavigation({
    items,
    onNavigate,
}: {
    items: NavigationItem[];
    onNavigate: () => void;
}) {
    return (
        <ul className="space-y-1">
            {items.map((item, index) => {
                const Icon = navigationIcon(
                    item.url ?? item.children?.find((child) => child.url)?.url,
                );
                const label = item.count
                    ? `${item.label} (${item.count})`
                    : item.label;
                return (
                    <li key={`${item.label}-${index}`}>
                        {item.children?.length ? (
                            <SidebarRailGroup
                                label={item.label}
                                icon={Icon}
                                active={
                                    item.active || containsActive(item.children)
                                }
                                count={item.count}
                            >
                                <RailMenuItems
                                    items={
                                        item.url
                                            ? [
                                                  {
                                                      ...item,
                                                      children: undefined,
                                                  },
                                                  ...item.children,
                                              ]
                                            : item.children
                                    }
                                    onNavigate={onNavigate}
                                />
                            </SidebarRailGroup>
                        ) : item.url ? (
                            <SidebarItem
                                asChild
                                active={item.active}
                                className="size-11 justify-center p-0"
                                title={label}
                                aria-label={label}
                                onClick={onNavigate}
                            >
                                {item.inertia ? (
                                    <Link href={item.url}>
                                        <Icon
                                            aria-hidden="true"
                                            className="size-[18px]"
                                        />
                                    </Link>
                                ) : (
                                    <a href={item.url}>
                                        <Icon
                                            aria-hidden="true"
                                            className="size-[18px]"
                                        />
                                    </a>
                                )}
                            </SidebarItem>
                        ) : (
                            <div
                                role="separator"
                                aria-label={item.label}
                                className="mx-2 my-3 border-t"
                            />
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export default function AdminLayout({
    title,
    description,
    children,
    actions,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    actions?: ReactNode;
}) {
    const t = useAdminTranslation();
    const page = usePage<SharedProps>();
    const { admin } = page.props;
    initializeAdminSession(admin.session);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        try {
            return (
                localStorage.getItem("kern-admin-sidebar-collapsed") === "true"
            );
        } catch {
            return false;
        }
    });
    function labelNavigation(items: NavigationItem[]): NavigationItem[] {
        return items.map((item) => {
            const path = item.url
                ? new URL(item.url, window.location.origin).pathname.replace(
                      /\/$/,
                      "",
                  )
                : "";
            const label = /\/contacts\/custom-fields$/.test(path)
                ? t("Contact form fields")
                : /\/custom-fields$/.test(path)
                  ? t("Field groups")
                  : item.label;
            return {
                ...item,
                label,
                children: item.children
                    ? labelNavigation(item.children)
                    : undefined,
            };
        });
    }
    const navigation = labelNavigation(
        activeNavigation(
            admin.navigation,
            new URL(page.url, window.location.origin).href,
            admin.urls.dashboard,
        ),
    );
    function toggleSidebar() {
        const next = !sidebarCollapsed;
        setSidebarCollapsed(next);
        try {
            localStorage.setItem("kern-admin-sidebar-collapsed", String(next));
        } catch {}
    }
    return (
        <div
            className="admin-shell min-h-screen"
            style={
                {
                    "--admin-sidebar-width": sidebarCollapsed
                        ? "4.5rem"
                        : "16rem",
                } as CSSProperties
            }
        >
            <Head title={title} />
            <AppearanceBridge appearance={admin.appearance} />
            <a
                href="#admin-content"
                className="sr-only fixed left-4 top-4 z-[80] rounded-lg bg-primary px-4 py-3 text-sm text-primary-foreground focus:not-sr-only"
            >
                {t("Skip to content")}
            </a>
            <Sidebar
                open={mobileOpen}
                onOpenChange={setMobileOpen}
                collapsed={sidebarCollapsed}
            >
                {(compact) => (
                    <>
                        <div
                            className={cn(
                                "flex shrink-0 border-b",
                                compact
                                    ? "flex-col items-center gap-1 py-3"
                                    : "min-h-16 items-center gap-2 px-3 py-2 pe-12 md:pe-3",
                            )}
                        >
                            <Link
                                href={admin.urls.dashboard}
                                aria-label={admin.name}
                                title={compact ? admin.name : undefined}
                                className={cn(
                                    "flex min-w-0 items-center gap-3 text-base font-semibold tracking-tight",
                                    compact
                                        ? "size-9 justify-center"
                                        : "flex-1 px-2",
                                )}
                            >
                                {admin.appearance?.logo ? (
                                    <img
                                        src={admin.appearance.logo}
                                        alt={admin.name}
                                        className="max-w-full object-contain"
                                        style={{
                                            maxHeight: compact
                                                ? 32
                                                : admin.appearance.logoHeight,
                                        }}
                                    />
                                ) : (
                                    <>
                                        <span
                                            aria-hidden="true"
                                            className="flex size-8 shrink-0 items-center justify-center rounded-md bg-foreground text-base font-semibold text-background"
                                        >
                                            K
                                        </span>
                                        {!compact && (
                                            <span className="truncate">
                                                {admin.name}
                                            </span>
                                        )}
                                    </>
                                )}
                            </Link>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="hidden shrink-0 md:inline-flex"
                                onClick={toggleSidebar}
                                aria-label={t(
                                    compact
                                        ? "Expand sidebar"
                                        : "Collapse sidebar",
                                )}
                                title={t(
                                    compact
                                        ? "Expand sidebar"
                                        : "Collapse sidebar",
                                )}
                                aria-expanded={!compact}
                                aria-controls="admin-sidebar"
                            >
                                {compact ? (
                                    <PanelLeftOpen
                                        aria-hidden="true"
                                        className="rtl:rotate-180"
                                    />
                                ) : (
                                    <PanelLeftClose
                                        aria-hidden="true"
                                        className="rtl:rotate-180"
                                    />
                                )}
                            </Button>
                        </div>
                        <nav
                            aria-label={t("Administration")}
                            className="flex min-h-0 flex-1 flex-col"
                        >
                            <SidebarScrollArea
                                storageKey={`kern-admin-navigation-scroll:${new URL(admin.urls.dashboard, window.location.origin).pathname}`}
                                collapsed={compact}
                            >
                                <p
                                    className={cn(
                                        "px-3 pb-2 text-xs font-medium text-muted-foreground",
                                        compact && "sr-only",
                                    )}
                                >
                                    {t("Workspace")}
                                </p>
                                {navigation.length ? (
                                    compact ? (
                                        <CompactNavigation
                                            items={navigation}
                                            onNavigate={() =>
                                                setMobileOpen(false)
                                            }
                                        />
                                    ) : (
                                        <Navigation
                                            items={navigation}
                                            showIcons={
                                                admin.appearance
                                                    ?.showMenuItemIcon ?? true
                                            }
                                            onNavigate={() =>
                                                setMobileOpen(false)
                                            }
                                        />
                                    )
                                ) : (
                                    <p
                                        role="status"
                                        className="px-3 py-6 text-center text-sm leading-relaxed text-muted-foreground"
                                    >
                                        {t(
                                            "No menus found. Try another keyword.",
                                        )}
                                    </p>
                                )}
                            </SidebarScrollArea>
                        </nav>
                    </>
                )}
            </Sidebar>
            <div className="min-w-0 md:ms-[var(--admin-sidebar-width)]">
                <header className="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b bg-card px-4 max-[400px]:gap-1 max-[400px]:px-2 sm:px-6 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3 max-[400px]:shrink-0">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="shrink-0 max-xl:size-11 md:hidden"
                            onClick={() => setMobileOpen(true)}
                            aria-label={t("Toggle navigation")}
                            aria-expanded={mobileOpen}
                            aria-haspopup="dialog"
                            aria-controls="admin-mobile-sidebar"
                        >
                            <Menu aria-hidden="true" />
                        </Button>
                        <nav
                            aria-label={t("Breadcrumb")}
                            className="hidden min-w-0 overflow-hidden md:block"
                        >
                            <ol className="flex items-center gap-2 text-sm">
                                <li>
                                    <Link
                                        href={admin.urls.dashboard}
                                        className="flex items-center gap-2 text-muted-foreground hover:text-foreground"
                                        aria-label={t("Dashboard")}
                                    >
                                        <LayoutDashboard
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        <span className="hidden sm:inline">
                                            {t("Dashboard")}
                                        </span>
                                    </Link>
                                </li>
                                {title !== t("Dashboard") && (
                                    <>
                                        <li aria-hidden="true">
                                            <ChevronRight className="size-3.5 text-muted-foreground/60 rtl:rotate-180" />
                                        </li>
                                        <li
                                            aria-current="page"
                                            className="truncate font-medium"
                                        >
                                            {title}
                                        </li>
                                    </>
                                )}
                            </ol>
                        </nav>
                    </div>
                    <div className="flex shrink-0 items-center gap-1 max-[400px]:gap-0 max-xl:[&>button]:size-11 max-xl:[&>button]:p-0 max-xl:[&>a]:size-11 max-xl:[&>button>span.hidden]:hidden max-xl:[&>button>kbd]:hidden sm:gap-2">
                        <GlobalSearch
                            url={admin.urls.search}
                            onNavigate={() => setMobileOpen(false)}
                        />
                        <ThemeSwitch />
                        {admin.header && (
                            <HeaderNotifications header={admin.header} />
                        )}
                        {admin.user && (
                            <>
                                <span
                                    aria-hidden="true"
                                    className="mx-1 hidden h-5 border-l xl:block"
                                />
                                <DropdownMenu
                                    label={t("Account menu")}
                                    trigger={
                                        <>
                                            <span className="hidden max-w-32 truncate text-sm xl:inline 2xl:max-w-40">
                                                {admin.user.name}
                                            </span>
                                            <span
                                                aria-hidden="true"
                                                className="flex size-7 items-center justify-center rounded-full bg-secondary text-xs font-medium text-foreground"
                                            >
                                                {admin.user.name
                                                    .slice(0, 1)
                                                    .toUpperCase()}
                                            </span>
                                            <ChevronDown
                                                aria-hidden="true"
                                                className="hidden size-3 xl:block"
                                            />
                                        </>
                                    }
                                >
                                    {admin.urls.profile && (
                                        <DropdownMenuItem asChild>
                                            <Link href={admin.urls.profile}>
                                                <UserRound aria-hidden="true" />
                                                {t("Profile")}
                                            </Link>
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem asChild>
                                        <a href={admin.urls.logout}>
                                            <LogOut aria-hidden="true" />
                                            {t("Sign out")}
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenu>
                            </>
                        )}
                    </div>
                </header>
                <main
                    id="admin-content"
                    tabIndex={-1}
                    className="admin-main mx-auto max-w-[1440px] p-4 outline-none sm:px-6 sm:py-5 lg:px-8"
                    data-container-width={
                        admin.appearance?.containerWidth ?? undefined
                    }
                >
                    <ToastHost />
                    <ConfirmHost />
                    <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                        {actions && (
                            <div className="flex shrink-0 items-center gap-2">
                                {actions}
                            </div>
                        )}
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}
