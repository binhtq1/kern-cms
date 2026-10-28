import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
import {
    Clock,
    Files,
    FileText,
    Image,
    Music,
    Star,
    Trash2,
    Video,
} from "lucide-react";
import type { Dispatch, SetStateAction } from "react";
export function MediaNavigation({
    view,
    setView,
    setFolder,
    setPage,
    filter,
    setFilter,
}: {
    view: string;
    setView: Dispatch<SetStateAction<string>>;
    setFolder: Dispatch<SetStateAction<string | number>>;
    setPage: Dispatch<SetStateAction<number>>;
    filter: string;
    setFilter: Dispatch<SetStateAction<string>>;
}) {
    const t = useAdminTranslation();
    return (
        <aside className="hidden self-stretch border-r bg-muted/15 py-3 lg:block">
            <p className="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("Library")}
            </p>
            <nav aria-label={t("Media views")} className="space-y-1 px-2">
                {[
                    {
                        value: "all_media",
                        label: "All media",
                        icon: Files,
                    },
                    {
                        value: "recent",
                        label: "Recent",
                        icon: Clock,
                    },
                    {
                        value: "favorites",
                        label: "Favorites",
                        icon: Star,
                    },
                    {
                        value: "trash",
                        label: "Trash",
                        icon: Trash2,
                    },
                ].map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        aria-current={view === item.value ? "page" : undefined}
                        className={cn(
                            "flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring",
                            view === item.value &&
                                "bg-accent font-medium text-primary-ink",
                        )}
                        onClick={() => {
                            setView(item.value);
                            setFolder(0);
                            setPage(1);
                        }}
                    >
                        <item.icon size={16} />
                        {t(item.label)}
                    </button>
                ))}
            </nav>
            <p className="mt-4 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("File type")}
            </p>
            <nav aria-label={t("Media file types")} className="space-y-1 px-2">
                {[
                    {
                        value: "everything",
                        label: "All files",
                        icon: Files,
                    },
                    {
                        value: "image",
                        label: "Images",
                        icon: Image,
                    },
                    {
                        value: "document",
                        label: "Documents",
                        icon: FileText,
                    },
                    { value: "video", label: "Video", icon: Video },
                    { value: "audio", label: "Audio", icon: Music },
                ].map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        aria-pressed={filter === item.value}
                        className={cn(
                            "flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring",
                            filter === item.value &&
                                "bg-accent font-medium text-primary-ink",
                        )}
                        onClick={() => {
                            setFilter(item.value);
                            setPage(1);
                        }}
                    >
                        <item.icon size={16} />
                        {t(item.label)}
                    </button>
                ))}
            </nav>
        </aside>
    );
}
