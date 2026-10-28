import { useEffect, useRef, useState } from "react";
import axios from "axios";
import { router } from "@inertiajs/react";
import { Columns3, Download, RotateCw } from "lucide-react";
import { Button } from "./ui/button";
import { Checkbox } from "./ui/checkbox";
import { DropdownMenu, DropdownMenuItem } from "./ui/dropdown-menu";
import { notify } from "./toast";
import { useAdminTranslation } from "@admin/lib/translate";

export type TableVisibility = {
    table: string;
    url: string;
    columns: Record<string, number>;
};

export function TableTools({
    buttons,
    columns,
    visibility,
    onVisibilityChange,
    indexUrl,
    filters,
    hasExport = false,
    disabled = false,
}: {
    buttons: string[];
    columns: { key: string; label: string }[];
    visibility?: TableVisibility;
    onVisibilityChange: (value: Record<string, number>) => void;
    indexUrl: string;
    filters: Record<string, unknown>;
    hasExport?: boolean;
    disabled?: boolean;
}) {
    const t = useAdminTranslation();
    const [pending, setPending] = useState<
        "reload" | "export" | "visibility" | null
    >(null);
    const active = useRef<object | null>(null);
    const cancel = useRef<(() => void) | null>(null);
    const busy = disabled || pending !== null;
    useEffect(
        () => () => {
            active.current = null;
            cancel.current?.();
        },
        [],
    );
    function begin(action: NonNullable<typeof pending>) {
        if (disabled || active.current) return null;
        const request = {};
        active.current = request;
        setPending(action);
        return request;
    }
    function finish(request: object) {
        if (active.current !== request) return;
        active.current = null;
        cancel.current = null;
        setPending(null);
    }
    async function toggle(key: string) {
        if (!visibility) return;
        const request = begin("visibility");
        if (!request) return;
        const controller = new AbortController();
        cancel.current = () => controller.abort();
        const next = {
            ...visibility.columns,
            [key]: Number(visibility.columns[key] === 0),
        };
        try {
            const { data } = await axios.put(
                visibility.url,
                {
                    table: visibility.table,
                    columns_visibility: next,
                },
                { signal: controller.signal },
            );
            if (active.current !== request) return;
            if (data.error) throw new Error(data.message);
            onVisibilityChange(next);
        } catch {
            if (active.current === request)
                notify(t("Unable to save changes"), true);
        } finally {
            finish(request);
        }
    }
    async function download(format: "csv" | "xlsx") {
        const request = begin("export");
        if (!request) return;
        const controller = new AbortController();
        cancel.current = () => controller.abort();
        try {
            const response = await axios.get(indexUrl, {
                params: { ...filters, _export: format },
                responseType: "blob",
                signal: controller.signal,
            });
            if (active.current !== request) return;
            if (!response.headers["content-disposition"])
                throw new Error("Export failed");
            const url = URL.createObjectURL(response.data);
            const anchor = document.createElement("a");
            anchor.href = url;
            anchor.download =
                /filename="?([^";]+)/.exec(
                    String(response.headers["content-disposition"]),
                )?.[1] ?? `export.${format}`;
            anchor.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            notify(t("Export complete"));
        } catch {
            if (active.current === request) notify(t("Export failed"), true);
        } finally {
            finish(request);
        }
    }
    return (
        <>
            {buttons.includes("reload") && (
                <Button
                    variant="outline"
                    disabled={busy}
                    loading={pending === "reload"}
                    onClick={() => {
                        const request = begin("reload");
                        if (!request) return;
                        let started = false;
                        try {
                            router.reload({
                                onStart: () => {
                                    started = true;
                                },
                                onCancelToken: (token) => {
                                    if (active.current !== request) {
                                        token.cancel();
                                        return;
                                    }
                                    cancel.current = () => token.cancel();
                                },
                                onFinish: () => finish(request),
                            });
                            if (!started) finish(request);
                        } catch (error) {
                            finish(request);
                            throw error;
                        }
                    }}
                >
                    {pending !== "reload" && <RotateCw aria-hidden="true" />}
                    {t("Reload")}
                </Button>
            )}
            {buttons.includes("visibility") && visibility && (
                <DropdownMenu
                    label={t("Columns")}
                    disabled={busy}
                    loading={pending === "visibility"}
                    trigger={
                        <>
                            {pending !== "visibility" && (
                                <Columns3 aria-hidden="true" />
                            )}
                            {t("Columns")}
                        </>
                    }
                >
                    {columns.map((column) => (
                        <DropdownMenuItem
                            key={column.key}
                            role="menuitemcheckbox"
                            aria-checked={visibility.columns[column.key] !== 0}
                            disabled={busy}
                            onSelect={(event) => {
                                event.preventDefault();
                                void toggle(column.key);
                            }}
                        >
                            <Checkbox
                                tabIndex={-1}
                                className="pointer-events-none"
                                aria-hidden="true"
                                checked={visibility.columns[column.key] !== 0}
                            />
                            {t(column.label)}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenu>
            )}
            {buttons.includes("export") && !hasExport && (
                <DropdownMenu
                    label={t("Export")}
                    disabled={busy}
                    loading={pending === "export"}
                    trigger={
                        <>
                            {pending !== "export" && (
                                <Download aria-hidden="true" />
                            )}
                            {t("Export")}
                        </>
                    }
                >
                    <DropdownMenuItem
                        disabled={busy}
                        onSelect={() => void download("csv")}
                    >
                        CSV
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={busy}
                        onSelect={() => void download("xlsx")}
                    >
                        Excel
                    </DropdownMenuItem>
                </DropdownMenu>
            )}
        </>
    );
}
