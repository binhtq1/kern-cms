import * as Tooltip from "@radix-ui/react-tooltip";
import { Funnel } from "lucide-react";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import { Button } from "./ui/button";

export function AdvancedFiltersButton({
    expanded,
    panelId,
    appliedCount = 0,
    onToggle,
}: {
    expanded: boolean;
    panelId: string;
    appliedCount?: number;
    onToggle: () => void;
}) {
    const t = useAdminTranslation();
    const label = t("Advanced filters");
    const appliedLabel = t("Applied advanced filters: :count", {
        count: appliedCount,
    });

    return (
        <span className="relative inline-flex shrink-0">
            <Tooltip.Provider delayDuration={350}>
                <Tooltip.Root>
                    <Tooltip.Trigger asChild>
                        <Button
                            variant="outline"
                            size="icon"
                            className={cn(
                                "relative",
                                expanded &&
                                    "border-primary bg-primary/10 text-primary-ink hover:bg-primary/15 hover:text-primary-ink",
                            )}
                            aria-label={
                                appliedCount > 0
                                    ? `${label}. ${appliedLabel}`
                                    : label
                            }
                            aria-expanded={expanded}
                            aria-controls={panelId}
                            onClick={onToggle}
                        >
                            <Funnel aria-hidden="true" />
                            {appliedCount > 0 && (
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute -end-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none tabular-nums text-primary-foreground ring-2 ring-card"
                                >
                                    {appliedCount}
                                </span>
                            )}
                        </Button>
                    </Tooltip.Trigger>
                    <Tooltip.Portal>
                        <Tooltip.Content
                            sideOffset={6}
                            collisionPadding={12}
                            className="z-50 rounded-md border bg-popover px-2 py-1 text-xs text-popover-foreground shadow-md"
                        >
                            {label}
                        </Tooltip.Content>
                    </Tooltip.Portal>
                </Tooltip.Root>
            </Tooltip.Provider>
            <span role="status" aria-atomic="true" className="sr-only">
                {appliedLabel}
            </span>
        </span>
    );
}
