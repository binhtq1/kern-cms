import { Field } from "@admin/components/field";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { useAdminDate } from "@admin/lib/date";
import { useAdminTranslation } from "@admin/lib/translate";
import { Copy, File, Folder, Info, X } from "lucide-react";
import type { Dispatch, SetStateAction } from "react";
import type { Item } from "../lib/media-types";
import { mediaShareText, type MediaShareType } from "../Media/media-share";
export function MediaDetails({
    setSelected,
    selected,
    view,
    copyText,
    open,
}: {
    setSelected: Dispatch<SetStateAction<Item[]>>;
    selected: Item[];
    view: string;
    copyText: (value: string) => Promise<void>;
    open: (item: Item) => void;
}) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    return (
        <aside
            className="min-w-0 space-y-4 self-stretch border-t bg-muted/10 p-4 lg:col-span-2 xl:col-span-1 xl:border-t-0 xl:border-l"
            aria-label={t("File details")}
        >
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold">{t("File details")}</h2>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label={t("Close details")}
                    onClick={() => setSelected([])}
                >
                    <X size={15} />
                </Button>
            </div>
            <div className="flex aspect-square items-center justify-center overflow-hidden rounded-md border bg-muted/30">
                {selected[0].is_folder ? (
                    <Folder size={64} style={{ color: selected[0].color }} />
                ) : selected[0].mime_type?.startsWith("image/") ? (
                    <img
                        src={
                            selected[0].preview_url ??
                            selected[0].full_url ??
                            selected[0].indirect_url ??
                            selected[0].thumb
                        }
                        alt={selected[0].alt ?? ""}
                        className="size-full object-contain"
                    />
                ) : (
                    <File size={64} className="text-muted-foreground" />
                )}
            </div>
            <h3 className="break-all text-sm font-medium">
                {selected[0].name}
            </h3>
            <dl className="space-y-3 text-xs">
                {[
                    [t("Size"), selected[0].size],
                    [t("File type"), selected[0].mime_type],
                    [t("Created at"), formatDate(selected[0].created_at, true)],
                    [t("Alt text"), selected[0].alt],
                ]
                    .filter(([, value]) => value)
                    .map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="mt-1 break-all">{value}</dd>
                        </div>
                    ))}
            </dl>
            {!selected[0].is_folder &&
                view !== "trash" &&
                [
                    ["url", "URL", "Copy link"],
                    ["indirect_url", "Indirect URL", "Copy indirect link"],
                ].map(([type, label, action]) => {
                    const value = mediaShareText(
                        selected,
                        type as MediaShareType,
                    );
                    return value ? (
                        <Field key={type} label={t(label)}>
                            {(id) => (
                                <div className="flex gap-2">
                                    <Input
                                        id={id}
                                        value={value}
                                        readOnly
                                        onFocus={(event) =>
                                            event.currentTarget.select()
                                        }
                                    />
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        aria-label={t(action)}
                                        onClick={() => void copyText(value)}
                                    >
                                        <Copy size={15} />
                                    </Button>
                                </div>
                            )}
                        </Field>
                    ) : null;
                })}
            {!selected[0].is_folder && (
                <Button
                    variant="outline"
                    className="w-full"
                    onClick={() => open(selected[0])}
                >
                    <Info size={15} />
                    {t("Preview")}
                </Button>
            )}
        </aside>
    );
}
