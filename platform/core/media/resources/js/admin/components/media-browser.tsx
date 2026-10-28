import { Checkbox } from "@admin/components/ui/checkbox";
import { LoadingIndicator } from "@admin/components/loading-indicator";
import { useAdminDate } from "@admin/lib/date";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import { File, Folder, Image } from "lucide-react";
import type { Dispatch, RefObject, SetStateAction } from "react";
import { useState } from "react";
import { mediaItemKey, selectMediaItems } from "../lib/media-selection";
import type { Item } from "../lib/media-types";
export function MediaBrowser({
    permissions,
    view,
    busy,
    upload,
    loading,
    all,
    grid,
    selected,
    toggle,
    open,
    selectionAnchor,
    setSelected,
}: {
    permissions: string[];
    view: string;
    busy: boolean;
    upload: (files: FileList | null) => Promise<void>;
    loading: boolean;
    all: Item[];
    grid: boolean;
    selected: Item[];
    toggle: (item: Item) => void;
    open: (item: Item) => void;
    selectionAnchor: RefObject<string | null>;
    setSelected: Dispatch<SetStateAction<Item[]>>;
}) {
    const t = useAdminTranslation();
    const key = mediaItemKey;
    const formatDate = useAdminDate();
    const [dropActive, setDropActive] = useState(false);
    return (
        <div
            onDragOver={(event) => {
                if (
                    permissions.includes("files.create") &&
                    view !== "trash" &&
                    event.dataTransfer.types.includes("Files")
                ) {
                    event.preventDefault();
                    setDropActive(true);
                }
            }}
            onDragLeave={(event) => {
                if (
                    !(event.relatedTarget instanceof Node) ||
                    !event.currentTarget.contains(event.relatedTarget)
                )
                    setDropActive(false);
            }}
            onDrop={(event) => {
                event.preventDefault();
                setDropActive(false);
                if (
                    !busy &&
                    permissions.includes("files.create") &&
                    view !== "trash"
                )
                    void upload(event.dataTransfer.files);
            }}
            aria-busy={loading}
            className={cn(
                "relative min-h-60 p-3",
                dropActive && "ring-2 ring-inset ring-primary bg-primary/5",
                loading && "pointer-events-none",
                loading && all.length > 0 && "opacity-60",
            )}
        >
            {dropActive && (
                <div className="pointer-events-none absolute inset-0 z-20 flex items-center justify-center rounded bg-card/95 text-sm font-medium text-primary-ink">
                    {t("Drop files to upload")}
                </div>
            )}
            {loading && all.length === 0 ? (
                <LoadingIndicator
                    label={t("Loading…")}
                    className="flex min-h-60"
                    iconClassName="size-5"
                />
            ) : all.length === 0 ? (
                <div className="flex min-h-60 flex-col items-center justify-center gap-3 text-muted-foreground">
                    <Image className="size-7" />
                    <p>{t("No media found")}</p>
                </div>
            ) : (
                <div
                    className={
                        grid
                            ? "grid grid-cols-2 gap-2 sm:grid-cols-3 2xl:grid-cols-5"
                            : "divide-y"
                    }
                >
                    {all.map((item) => (
                        <div
                            key={key(item)}
                            className={cn(
                                grid
                                    ? "group relative rounded-md border"
                                    : "flex items-center gap-3 py-2",
                                selected.some(
                                    (value) => key(value) === key(item),
                                ) && "bg-accent ring-1 ring-primary",
                            )}
                        >
                            <div
                                className={
                                    grid
                                        ? "absolute left-1 top-1 z-10 rounded bg-card/90"
                                        : "shrink-0"
                                }
                            >
                                <Checkbox
                                    disabled={loading}
                                    aria-label={`${t("Select")} ${item.name}`}
                                    checked={selected.some(
                                        (value) => key(value) === key(item),
                                    )}
                                    onCheckedChange={(checked) => toggle(item)}
                                />
                            </div>
                            <button
                                type="button"
                                disabled={loading}
                                className={cn(
                                    "min-w-0 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring",
                                    grid
                                        ? "w-full p-3"
                                        : "flex flex-1 items-center gap-3",
                                )}
                                onClick={(event) => {
                                    const next = selectMediaItems(
                                        all,
                                        selected,
                                        item,
                                        event,
                                        selectionAnchor.current,
                                    );
                                    if (next.openFolder) {
                                        open(item);
                                        return;
                                    }
                                    if (event.ctrlKey || event.metaKey)
                                        toggle(item);
                                    else setSelected(next.selected);
                                    selectionAnchor.current = next.anchor;
                                }}
                                onDoubleClick={() => open(item)}
                            >
                                {item.is_folder ? (
                                    <Folder
                                        style={
                                            item.color
                                                ? {
                                                      color: item.color,
                                                  }
                                                : undefined
                                        }
                                        className={
                                            grid
                                                ? "mx-auto my-6 size-14 text-muted-foreground"
                                                : "size-9 text-muted-foreground"
                                        }
                                    />
                                ) : item.thumb ||
                                  item.mime_type?.startsWith("image/") ? (
                                    <img
                                        src={
                                            item.thumb ??
                                            item.full_url ??
                                            item.indirect_url ??
                                            ""
                                        }
                                        alt=""
                                        className={
                                            grid
                                                ? "aspect-square w-full rounded object-contain"
                                                : "size-10 rounded object-cover"
                                        }
                                        loading="lazy"
                                    />
                                ) : (
                                    <File
                                        className={
                                            grid
                                                ? "mx-auto my-6 size-14 text-muted-foreground"
                                                : "size-9 text-muted-foreground"
                                        }
                                    />
                                )}
                                <span
                                    className={cn(
                                        "block min-w-0",
                                        grid && "mt-3",
                                    )}
                                >
                                    <span className="block truncate text-sm font-medium">
                                        {item.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {item.is_folder
                                            ? t("Folder")
                                            : item.size}
                                    </span>
                                </span>
                            </button>
                            {!grid && (
                                <span className="hidden text-xs text-muted-foreground sm:block">
                                    {formatDate(item.updated_at, true)}
                                </span>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
