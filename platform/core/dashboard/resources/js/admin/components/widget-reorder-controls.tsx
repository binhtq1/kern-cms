import { Button } from "@admin/components/ui/button";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import { ArrowDown, ArrowUp, GripVertical } from "lucide-react";
import type { Widget } from "../lib/dashboard-types";
export function WidgetReorderControls({
    widget,
    sort,
    busy,
    index,
    normal,
    move,
}: {
    widget: Widget;
    sort: ReturnType<typeof usePointerSort>;
    busy: boolean;
    index: number;
    normal: Widget[];
    move: (source: string, target: string, after?: boolean) => Promise<void>;
}) {
    const t = useAdminTranslation();
    return (
        <div className="flex shrink-0 items-center">
            <button
                type="button"
                {...sort.handle(widget.name, widget.title)}
                disabled={busy}
                title={t("Drag to reorder")}
                aria-label={`${t("Drag to reorder")} ${widget.title}`}
                className="rounded p-1.5 text-muted-foreground cursor-grab focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:cursor-grabbing"
            >
                <GripVertical className="size-4" />
            </button>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                disabled={busy || index === 0}
                aria-label={`${t("Move up")} ${widget.title}`}
                onClick={() => void move(widget.name, normal[index - 1].name)}
            >
                <ArrowUp className="size-3" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                disabled={busy || index === normal.length - 1}
                aria-label={`${t("Move down")} ${widget.title}`}
                onClick={() => void move(widget.name, normal[index + 1].name)}
            >
                <ArrowDown className="size-3" />
            </Button>
        </div>
    );
}
