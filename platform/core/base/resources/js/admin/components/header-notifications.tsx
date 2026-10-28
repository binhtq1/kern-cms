import { useConfirmDialog } from "@admin/components/confirm-dialog";
import { LoadingIndicator } from "./loading-indicator";
import axios from "axios";
import { Link } from "@inertiajs/react";
import { Bell, Globe, Mail, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import type { SharedProps } from "@admin/types";
import { useAdminTranslation } from "@admin/lib/translate";
import { useAdminDate } from "@admin/lib/date";
import { assertAdminResult } from "@admin/lib/admin-result";
import { notify } from "./toast";
import { SafeRichText } from "./safe-rich-text";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from "./ui/dropdown-menu";

type Header = NonNullable<SharedProps["admin"]["header"]>;
type Notification = {
    id: number | string;
    title: string;
    description: string;
    descriptionHtml: string;
    unread: boolean;
    createdAt: string | null;
    actionLabel: string;
    actionUrl: string | null;
    deleteUrl: string;
};
type Results = {
    count: number;
    items: Notification[];
    pagination: { page: number; lastPage: number; total: number };
};

function Count({ count }: { count: number }) {
    return count > 0 ? (
        <span
            aria-hidden="true"
            className="absolute -right-1 -top-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-4 text-white"
        >
            {count > 99 ? "99+" : count}
        </span>
    ) : null;
}

export function HeaderNotifications({ header }: { header: Header }) {
    const t = useAdminTranslation();
    const { confirm, confirmationDialog } = useConfirmDialog();
    const date = useAdminDate();
    const [open, setOpen] = useState(false);
    const [page, setPage] = useState(1);
    const [version, setVersion] = useState(0);
    const [count, setCount] = useState(header.notifications.count);
    const [results, setResults] = useState<Results | null>(null);
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => setCount(header.notifications.count), [header]);
    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        setLoading(true);
        setError("");
        axios
            .get(header.notifications.url, {
                params: { admin_json: 1, page },
                signal: controller.signal,
            })
            .then(({ data }) => {
                assertAdminResult(data, t("Unable to load notifications."));
                if (controller.signal.aborted) return;
                const next = data.data as Results;
                if (page > next.pagination.lastPage) {
                    setPage(next.pagination.lastPage);
                    return;
                }
                setResults(next);
                setCount(next.count);
            })
            .catch((error) => {
                if (!controller.signal.aborted)
                    setError(
                        error.response?.data?.message ||
                            t("Unable to load notifications."),
                    );
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [open, page, version, header.notifications.url, t]);

    async function update(
        url: string,
        method: "delete" | "put",
        confirmation?: string,
    ) {
        if (busy || (confirmation && !(await confirm(confirmation)))) return;
        setBusy(true);
        try {
            const { data } = await axios.request({ url, method });
            assertAdminResult(data, t("Could not update notifications."));
            notify(t("Notifications updated."));
            setVersion((current) => current + 1);
        } catch (error) {
            notify(
                axios.isAxiosError(error)
                    ? error.response?.data?.message ||
                          t("Could not update notifications.")
                    : t("Could not update notifications."),
                true,
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            {confirmationDialog}
            {header.website && (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="hidden sm:inline-flex"
                    aria-label={t("View website")}
                    title={t("View website")}
                >
                    <a
                        href={header.website}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Globe aria-hidden="true" />
                    </a>
                </Button>
            )}
            {header.contacts && (
                <DropdownMenu
                    label={t("Unread contacts")}
                    trigger={
                        <span className="relative">
                            <Mail aria-hidden="true" />
                            <Count count={header.contacts.items.length} />
                            <span className="sr-only">
                                {header.contacts.items.length}{" "}
                                {t("Unread contacts")}
                            </span>
                        </span>
                    }
                >
                    <div className="px-3 py-2 text-sm font-semibold">
                        {t("Unread contacts")}
                    </div>
                    {header.contacts.items.map((contact) => {
                        const details = (
                            <div className="min-w-0 max-w-64">
                                <p className="truncate font-medium">
                                    {contact.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {[contact.phone, contact.email]
                                        .filter(Boolean)
                                        .join(" · ")}
                                </p>
                                <time
                                    className="text-xs text-muted-foreground"
                                    dateTime={contact.createdAt ?? undefined}
                                >
                                    {date(contact.createdAt, true)}
                                </time>
                            </div>
                        );
                        return (
                            <DropdownMenuItem key={contact.id} asChild>
                                {header.contacts?.inertia ? (
                                    <Link href={contact.url}>{details}</Link>
                                ) : (
                                    <a href={contact.url}>{details}</a>
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                    {header.contacts.url && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                {header.contacts.inertia ? (
                                    <Link href={header.contacts.url}>
                                        {t("View all")}
                                    </Link>
                                ) : (
                                    <a href={header.contacts.url}>
                                        {t("View all")}
                                    </a>
                                )}
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenu>
            )}
            <Button
                variant="ghost"
                size="icon"
                aria-label={`${t("Notifications")}${count ? ` (${count} ${t("Unread")})` : ""}`}
                title={t("Notifications")}
                onClick={() => {
                    setPage(1);
                    setOpen(true);
                }}
            >
                <span className="relative">
                    <Bell aria-hidden="true" />
                    <Count count={count} />
                </span>
            </Button>
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title={`${t("Notifications")}${count ? ` (${count})` : ""}`}
                size="sm"
            >
                <div className="space-y-4">
                    {!!results?.pagination.total && (
                        <div className="flex flex-wrap gap-2 border-b pb-3">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={loading || busy || count === 0}
                                onClick={() =>
                                    void update(
                                        header.notifications.readAll,
                                        "put",
                                    )
                                }
                            >
                                {t("Mark all read")}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                className="text-destructive"
                                disabled={loading || busy}
                                onClick={() =>
                                    void update(
                                        header.notifications.clearAll,
                                        "delete",
                                        t(
                                            "Delete all notifications? This action cannot be undone.",
                                        ),
                                    )
                                }
                            >
                                {t("Clear all")}
                            </Button>
                        </div>
                    )}
                    {error ? (
                        <div
                            role="alert"
                            className="space-y-3 py-6 text-center"
                        >
                            <p className="text-sm text-destructive">{error}</p>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    setVersion((current) => current + 1)
                                }
                            >
                                {t("Try again")}
                            </Button>
                        </div>
                    ) : loading ? (
                        <LoadingIndicator
                            label={t("Loading...")}
                            className="flex py-8"
                        />
                    ) : results?.items.length ? (
                        <ul className="max-h-[55dvh] space-y-3 overflow-y-auto">
                            {results.items.map((item) => (
                                <li
                                    key={item.id}
                                    className={`rounded-md border p-3 ${item.unread ? "border-primary/30 bg-primary/5" : ""}`}
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="break-words text-sm font-semibold">
                                                {item.title}
                                            </p>
                                            <time
                                                className="text-xs text-muted-foreground"
                                                dateTime={
                                                    item.createdAt ?? undefined
                                                }
                                            >
                                                {date(item.createdAt, true)}
                                            </time>
                                            {item.unread && (
                                                <span className="ml-2 text-xs font-medium text-primary-ink">
                                                    {t("Unread")}
                                                </span>
                                            )}
                                            {item.description && (
                                                <SafeRichText
                                                    className="mt-2 text-muted-foreground"
                                                    html={
                                                        item.descriptionHtml ||
                                                        item.description
                                                    }
                                                />
                                            )}
                                            {item.actionUrl && (
                                                <a
                                                    href={item.actionUrl}
                                                    className="mt-3 inline-flex text-sm font-medium text-primary-ink underline underline-offset-4"
                                                >
                                                    {item.actionLabel}
                                                </a>
                                            )}
                                        </div>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                            disabled={busy || loading}
                                            aria-label={`${t("Delete notification")}: ${item.title}`}
                                            onClick={() =>
                                                void update(
                                                    item.deleteUrl,
                                                    "delete",
                                                )
                                            }
                                        >
                                            <Trash2
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="py-10 text-center">
                            <Bell
                                aria-hidden="true"
                                className="mx-auto mb-3 size-8 text-muted-foreground"
                            />
                            <p className="font-medium">
                                {t("No notifications")}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t("Please check again later.")}
                            </p>
                        </div>
                    )}
                    {results && results.pagination.lastPage > 1 && (
                        <nav
                            aria-label={t("Pagination")}
                            className="flex items-center justify-between border-t pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={loading || busy || page <= 1}
                                onClick={() =>
                                    setPage((current) => current - 1)
                                }
                            >
                                {t("Previous")}
                            </Button>
                            <span className="text-xs tabular-nums text-muted-foreground">
                                {page} / {results.pagination.lastPage}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    loading ||
                                    busy ||
                                    page >= results.pagination.lastPage
                                }
                                onClick={() =>
                                    setPage((current) => current + 1)
                                }
                            >
                                {t("Next")}
                            </Button>
                        </nav>
                    )}
                </div>
            </Dialog>
        </>
    );
}
