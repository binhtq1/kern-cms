import { useRef, type ComponentProps } from "react";
import { Search, X } from "lucide-react";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { cn } from "@admin/lib/utils";
import { useAdminTranslation } from "@admin/lib/translate";

export function SearchInput({
    value,
    onClear,
    className,
    ...props
}: ComponentProps<"input"> & { onClear?: () => void }) {
    const t = useAdminTranslation();
    const ref = useRef<HTMLDivElement>(null);
    return (
        <div ref={ref} className={cn("relative min-w-0", className)}>
            <Search
                aria-hidden="true"
                className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                aria-label={t("Search")}
                {...props}
                value={value}
                className={cn(
                    "ps-9 [&::-webkit-search-cancel-button]:appearance-none",
                    onClear && "pe-11",
                )}
            />
            {onClear && value && (
                <Button
                    size="icon"
                    variant="ghost"
                    className="absolute end-0 top-1/2 -translate-y-1/2"
                    aria-label={t("Clear search")}
                    onClick={() => {
                        onClear();
                        ref.current?.querySelector("input")?.focus();
                    }}
                >
                    <X />
                </Button>
            )}
        </div>
    );
}
