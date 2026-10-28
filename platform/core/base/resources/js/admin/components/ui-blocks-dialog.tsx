import { SearchInput } from "./search-input";
import { useState } from "react";
import { Search, Code2, Check } from "lucide-react";
import { Dialog } from "./ui/dialog";
import { Button } from "./ui/button";

import { validImageSource } from "./image-source";
import { cn, normalizeSearch } from "@admin/lib/utils";
import { useAdminTranslation } from "@admin/lib/translate";
import type { Shortcode } from "./rich-editor";

export function UiBlocksDialog({
    open,
    onOpenChange,
    blocks,
    onUse,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    blocks: Shortcode[];
    onUse: (block: Shortcode) => void;
}) {
    const t = useAdminTranslation();
    const [query, setQuery] = useState("");
    const [selected, setSelected] = useState("");
    const matches = blocks.filter((block) =>
        normalizeSearch(
            `${block.name} ${block.key} ${block.description ?? ""}`,
        ).includes(normalizeSearch(query)),
    );
    const chosen = matches.find((block) => block.key === selected);
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);
                if (!next) {
                    setQuery("");
                    setSelected("");
                }
            }}
            title={t("UI Blocks")}
            description={t(
                "Choose a block, configure its fields, then insert it into your content.",
            )}
            size="full"
            footer={
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        onClick={() => {
                            setQuery("");
                            setSelected("");
                            onOpenChange(false);
                        }}
                    >
                        {t("Close")}
                    </Button>
                    <Button
                        disabled={!chosen}
                        onClick={() => {
                            if (chosen) {
                                setQuery("");
                                setSelected("");
                                onUse(chosen);
                            }
                        }}
                    >
                        {t("Use")}
                    </Button>
                </div>
            }
        >
            <SearchInput
                className="mb-5"
                aria-label={t("Search UI Blocks")}
                placeholder={t("Search UI Blocks")}
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setSelected("");
                }}
                onClear={() => setQuery("")}
            />
            <div className="grid grid-cols-1 gap-4 p-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {matches.map((block) => (
                    <article
                        key={block.key}
                        className={cn(
                            "min-w-0 overflow-hidden rounded-lg border bg-card",
                            selected === block.key &&
                                "border-primary ring-1 ring-primary",
                        )}
                    >
                        <button
                            type="button"
                            aria-label={`${t("Select")} ${block.name}`}
                            aria-pressed={selected === block.key}
                            onClick={() => setSelected(block.key)}
                            className="block w-full text-left outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                        >
                            <div className="relative flex aspect-[16/9] items-center justify-center border-b bg-muted/30">
                                <Code2
                                    aria-hidden="true"
                                    className="absolute size-12 text-muted-foreground/30"
                                />
                                {block.previewImage &&
                                    validImageSource(block.previewImage) && (
                                        <img
                                            loading="lazy"
                                            src={block.previewImage}
                                            alt={block.name}
                                            className="relative size-full object-contain"
                                            onError={(event) => {
                                                event.currentTarget.style.visibility =
                                                    "hidden";
                                            }}
                                        />
                                    )}
                                {selected === block.key && (
                                    <span className="absolute right-2 top-2 rounded-full bg-primary p-1 text-primary-foreground">
                                        <Check
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                    </span>
                                )}
                            </div>
                            <div className="space-y-1 p-4">
                                <h3 className="font-medium">{block.name}</h3>
                                <p className="min-h-10 text-sm leading-5 text-muted-foreground">
                                    {block.description}
                                </p>
                            </div>
                        </button>
                        <div className="flex justify-end px-4 pb-4">
                            <Button
                                variant="outline"
                                size="sm"
                                aria-label={`${t("Use")} ${block.name}`}
                                onClick={() => {
                                    setQuery("");
                                    setSelected("");
                                    onUse(block);
                                }}
                            >
                                {t("Use")}
                            </Button>
                        </div>
                    </article>
                ))}
            </div>
            {!matches.length && (
                <p
                    role="status"
                    className="py-12 text-center text-sm text-muted-foreground"
                >
                    {t("No UI Blocks found.")}
                </p>
            )}
        </Dialog>
    );
}
