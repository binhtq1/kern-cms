import { SafeRichText } from "@admin/components/safe-rich-text";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { Head } from "@inertiajs/react";
import { FileText, Palette, Plug, Users } from "lucide-react";
import { ManageWidgetsDialog } from "../components/manage-widgets-dialog";
import { UpdateNotice } from "../components/update-notice";
import { WidgetLink } from "../components/widget-link";
import { WidgetPanel } from "../components/widget-panel";
import { WidgetReorderControls } from "../components/widget-reorder-controls";
import { useDashboardLayout } from "../hooks/use-dashboard-layout";
import type { Widget } from "../lib/dashboard-types";

const iconMap: Record<string, typeof Users> = {
    "ti ti-users": Users,
    "ti ti-files": FileText,
    "ti ti-plug": Plug,
    "ti ti-palette": Palette,
};
const colorMap: Record<string, string> = {
    pink: "bg-pink-500/10 text-pink-700 dark:text-pink-300",
    info: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
    success: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    yellow: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
};
export default function Dashboard({
    widgets,
    urls,
    setup,
    notices = [],
    updater,
}: {
    widgets: Widget[];
    urls: { order: string; visibility: string; setting: string };
    setup?: string;
    notices?: { html: string }[];
    updater?: { check: string; url: string } | null;
}) {
    const t = useAdminTranslation();
    const {
        manage,
        setManage,
        items,
        setItems,
        display,
        busy,
        error,
        setError,
        save,
        move,
        sort,
    } = useDashboardLayout(widgets, urls);
    const normal = display.filter(
        (widget) => widget.visible && widget.type === "widget",
    );
    return (
        <AdminLayout
            title={t("Dashboard")}
            actions={
                widgets.length > 0 && (
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => {
                            setItems(display);
                            setError("");
                            setManage(true);
                        }}
                    >
                        {t("Manage widgets")}
                    </Button>
                )
            }
        >
            <Head title={t("Dashboard")} />
            {sort.overlay}
            <UpdateNotice updater={updater} />
            {!!notices.length && (
                <div className="mb-5 space-y-3">
                    {notices.map((notice, index) => (
                        <div
                            key={index}
                            role="status"
                            className="rounded-lg border bg-muted/30 p-4"
                        >
                            <SafeRichText html={notice.html} />
                        </div>
                    ))}
                </div>
            )}
            {setup && !notices.length && (
                <Card className="mb-5">
                    <CardContent className="flex items-center justify-between gap-3">
                        <p className="text-sm">
                            {t("Complete your site setup")}
                        </p>
                        <Button asChild>
                            <a href={setup}>{t("Get started")}</a>
                        </Button>
                    </CardContent>
                </Card>
            )}
            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {display
                    .filter(
                        (widget) => widget.visible && widget.type !== "widget",
                    )
                    .map((widget) => {
                        const Icon = iconMap[widget.icon ?? ""] ?? FileText;
                        return (
                            <Card key={widget.name}>
                                <CardContent className="flex items-center gap-4">
                                    <span
                                        className={`rounded-md p-3 ${colorMap[widget.color ?? ""] ?? "bg-primary/10 text-primary"}`}
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="size-6"
                                        />
                                    </span>
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {widget.title}
                                        </p>
                                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                                            <WidgetLink url={widget.url}>
                                                {typeof widget.value ===
                                                "number"
                                                    ? widget.value.toLocaleString()
                                                    : widget.value}
                                            </WidgetLink>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
            </div>
            <div className="grid gap-5 xl:grid-cols-2">
                {normal.map((widget, index) => (
                    <div
                        key={widget.name}
                        data-sort-before={JSON.stringify([widget.name, false])}
                        data-sort-after={JSON.stringify([widget.name, true])}
                        className={`${widget.column?.includes("col-md-6") ? "" : "xl:col-span-2"} relative min-w-0 rounded-lg ${sort.target === JSON.stringify([widget.name, false]) ? "before:pointer-events-none before:absolute before:inset-x-0 before:-top-3 before:h-1 before:rounded before:bg-primary" : ""} ${sort.target === JSON.stringify([widget.name, true]) ? "after:pointer-events-none after:absolute after:inset-x-0 after:-bottom-3 after:h-1 after:rounded after:bg-primary" : ""} ${sort.active === widget.name ? "opacity-50" : ""}`}
                    >
                        <WidgetPanel
                            widget={widget}
                            settingUrl={urls.setting}
                            controls={
                                <WidgetReorderControls
                                    widget={widget}
                                    sort={sort}
                                    busy={busy}
                                    index={index}
                                    normal={normal}
                                    move={move}
                                />
                            }
                        />
                    </div>
                ))}
            </div>
            <ManageWidgetsDialog
                manage={manage}
                setManage={setManage}
                items={items}
                setItems={setItems}
                display={display}
                busy={busy}
                error={error}
                save={save}
            />
        </AdminLayout>
    );
}
