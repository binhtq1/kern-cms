import { useState } from "react";
import axios from "axios";
import { Download } from "lucide-react";
import {
    ContentResourceIndex,
    type ContentIndexProps,
} from "@admin/components/content-resource-index";
import {
    DropdownMenu,
    DropdownMenuItem,
} from "@admin/components/ui/dropdown-menu";
import { notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";

export default function Index(
    props: ContentIndexProps & {
        urls: ContentIndexProps["urls"] & { export: string };
    },
) {
    const t = useAdminTranslation();
    const [busy, setBusy] = useState(false);
    async function download(format: string) {
        if (busy) return;
        setBusy(true);
        try {
            const response = await axios.post(
                props.urls.export,
                {
                    format,
                    advanced: props.filters.advanced ?? [],
                    search: props.filters.search ?? "",
                    status: props.filters.status ?? "",
                },
                { responseType: "blob" },
            );
            if (
                !response.headers["content-disposition"] ||
                String(response.headers["content-type"]).includes("json")
            )
                throw new Error(t("Export failed"));
            const url = URL.createObjectURL(response.data);
            const a = document.createElement("a");
            a.href = url;
            a.download =
                /filename="?([^";]+)/.exec(
                    String(response.headers["content-disposition"]),
                )?.[1] ?? `contacts.${format}`;
            a.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            notify(t("Export complete"));
        } catch (error) {
            notify(
                error instanceof Error ? error.message : t("Export failed"),
                true,
            );
        } finally {
            setBusy(false);
        }
    }
    return (
        <ContentResourceIndex
            {...props}
            tableToolsExtra={
                <DropdownMenu
                    loading={busy}
                    disabled={busy}
                    trigger={
                        <>
                            {!busy && <Download aria-hidden="true" />}
                            {t("Export")}
                        </>
                    }
                    label={t("Export")}
                >
                    <DropdownMenuItem
                        disabled={busy}
                        onClick={() => void download("csv")}
                    >
                        CSV
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={busy}
                        onClick={() => void download("xlsx")}
                    >
                        Excel (.xlsx)
                    </DropdownMenuItem>
                </DropdownMenu>
            }
        />
    );
}
