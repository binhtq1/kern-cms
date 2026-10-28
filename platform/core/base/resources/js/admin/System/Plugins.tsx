import { Badge } from "@admin/components/ui/badge";
import { SearchInput } from "@admin/components/search-input";
import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import axios from "axios";
import { Puzzle } from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Card, CardContent, CardFooter } from "@admin/components/ui/card";
import { Button } from "@admin/components/ui/button";
import { Dialog } from "@admin/components/ui/dialog";
import { notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";

type Plugin = {
    id: string;
    name: string;
    description: string;
    version: string;
    active: boolean;
    image: string | null;
    author: string | null;
    authorUrl: string | null;
};
export default function Plugins({
    title,
    plugins,
    permissions,
    urls,
}: {
    title: string;
    plugins: Plugin[];
    permissions: { edit: boolean; remove: boolean };
    urls: { requirements: string; status: string; remove: string };
}) {
    const t = useAdminTranslation();
    const [query, setQuery] = useState("");
    const [filter, setFilter] = useState("all");
    const [pending, setPending] = useState<{
        plugin: Plugin;
        remove: boolean;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const searched = plugins.filter((plugin) =>
        `${plugin.name} ${plugin.author ?? ""} ${plugin.description}`
            .toLocaleLowerCase()
            .includes(query.trim().toLocaleLowerCase()),
    );
    const matches = (plugin: Plugin, status: string) =>
        status === "all" || plugin.active === (status === "active");
    const visible = searched.filter((plugin) => matches(plugin, filter));
    async function perform() {
        if (!pending) return;
        setBusy(true);
        try {
            if (!pending.remove && !pending.plugin.active) {
                const result = await axios.post(urls.requirements, {
                    name: pending.plugin.id,
                });
                if (result.data.error) throw new Error(result.data.message);
            }
            const response = await axios.request({
                method: pending.remove ? "delete" : "put",
                url: pending.remove
                    ? urls.remove.replace(
                          "__PLUGIN__",
                          encodeURIComponent(pending.plugin.id),
                      )
                    : urls.status,
                data: { name: pending.plugin.id },
            });
            if (response.data.error) throw new Error(response.data.message);
            notify(response.data.message || t("Saved"));
            setPending(null);
            router.reload();
        } catch (error) {
            notify(
                axios.isAxiosError(error)
                    ? error.response?.data?.message || error.message
                    : error instanceof Error
                      ? error.message
                      : t("Something went wrong"),
                true,
            );
        } finally {
            setBusy(false);
        }
    }
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <SearchInput
                    onClear={() => setQuery("")}
                    aria-label={t("Search plugins")}
                    placeholder={t("Search plugins")}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    className="sm:max-w-sm"
                />
                <div
                    role="group"
                    aria-label={t("Status")}
                    className="flex flex-wrap gap-2"
                >
                    {["all", "active", "inactive"].map((status) => (
                        <Button
                            key={status}
                            variant={filter === status ? "default" : "outline"}
                            aria-pressed={filter === status}
                            onClick={() => setFilter(status)}
                        >
                            {t(
                                status === "all"
                                    ? "All"
                                    : status === "active"
                                      ? "Active"
                                      : "Inactive",
                            )}{" "}
                            (
                            {
                                searched.filter((plugin) =>
                                    matches(plugin, status),
                                ).length
                            }
                            )
                        </Button>
                    ))}
                </div>
            </div>
            <p role="status" className="mb-3 text-sm text-muted-foreground">
                {t(":count results", { count: visible.length })}
            </p>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {visible.map((plugin) => (
                    <Card
                        key={plugin.id}
                        className="flex flex-col overflow-hidden"
                    >
                        <div className="flex aspect-[2/1] items-center justify-center border-b">
                            {plugin.image ? (
                                <img
                                    src={plugin.image}
                                    alt=""
                                    className="size-full object-contain dark:brightness-150"
                                    width={640}
                                    height={320}
                                    loading="lazy"
                                />
                            ) : (
                                <Puzzle
                                    aria-hidden="true"
                                    className="size-10 text-muted-foreground"
                                />
                            )}
                        </div>
                        <CardContent className="flex-1 space-y-3">
                            <div className="flex items-start justify-between gap-3">
                                <h2 className="font-semibold">{plugin.name}</h2>
                                <Badge
                                    variant={
                                        plugin.active ? "success" : "outline"
                                    }
                                >
                                    {t(plugin.active ? "Active" : "Inactive")}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {plugin.description}
                            </p>
                            <dl className="space-y-1 text-xs text-muted-foreground">
                                {plugin.author && (
                                    <div>
                                        <dt className="inline">
                                            {t("Author")}:{" "}
                                        </dt>
                                        <dd className="inline font-medium text-foreground">
                                            {plugin.authorUrl ? (
                                                <a
                                                    href={plugin.authorUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="underline underline-offset-4"
                                                >
                                                    {plugin.author}
                                                </a>
                                            ) : (
                                                plugin.author
                                            )}
                                        </dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="inline">{t("Version")}: </dt>
                                    <dd className="inline">{plugin.version}</dd>
                                </div>
                            </dl>
                        </CardContent>
                        {(permissions.edit ||
                            (permissions.remove && !plugin.active)) && (
                            <CardFooter className="flex-wrap">
                                {permissions.edit && (
                                    <Button
                                        variant={
                                            plugin.active
                                                ? "outline"
                                                : "default"
                                        }
                                        onClick={() =>
                                            setPending({
                                                plugin,
                                                remove: false,
                                            })
                                        }
                                    >
                                        {t(
                                            plugin.active
                                                ? "Deactivate"
                                                : "Activate",
                                        )}
                                    </Button>
                                )}
                                {permissions.remove && !plugin.active && (
                                    <Button
                                        variant="outline"
                                        className="text-destructive"
                                        onClick={() =>
                                            setPending({ plugin, remove: true })
                                        }
                                    >
                                        {t("Remove")}
                                    </Button>
                                )}
                            </CardFooter>
                        )}
                    </Card>
                ))}
            </div>
            {!visible.length && (
                <Card>
                    <CardContent className="py-12 text-center text-sm text-muted-foreground">
                        {t("No results found")}
                    </CardContent>
                </Card>
            )}
            <Dialog
                open={Boolean(pending)}
                onOpenChange={(open) => {
                    if (!busy && !open) setPending(null);
                }}
                title={pending?.plugin.name ?? ""}
                description={t(
                    pending?.remove
                        ? "Remove this plugin and its files permanently?"
                        : "Change this plugin status?",
                )}
            >
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setPending(null)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        loading={busy}
                        variant={pending?.remove ? "destructive" : "default"}
                        onClick={() => void perform()}
                    >
                        {t(
                            pending?.remove
                                ? "Remove"
                                : pending?.plugin.active
                                  ? "Deactivate"
                                  : "Activate",
                        )}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
