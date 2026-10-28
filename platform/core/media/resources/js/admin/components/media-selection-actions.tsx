import { Checkbox } from "@admin/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuItem,
} from "@admin/components/ui/dropdown-menu";
import { useAdminTranslation } from "@admin/lib/translate";
import type { Dispatch, RefObject, SetStateAction } from "react";
import type { Item } from "../lib/media-types";
import { mediaShareText, type MediaShareType } from "../Media/media-share";
export function MediaSelectionActions({
    loading,
    all,
    selected,
    setSelected,
    actionsLabel,
    view,
    busy,
    copyText,
    shareReturnFocus,
    setShareType,
    setShareOpen,
    can,
    folderColors,
    setFolderColor,
    setDialog,
    download,
    setText,
    setRenamePhysicalFile,
    action,
    setDestination,
    folder,
}: {
    loading: boolean;
    all: Item[];
    selected: Item[];
    setSelected: Dispatch<SetStateAction<Item[]>>;
    actionsLabel: RefObject<HTMLSpanElement | null>;
    view: string;
    busy: boolean;
    copyText: (value: string) => Promise<void>;
    shareReturnFocus: RefObject<HTMLButtonElement | null>;
    setShareType: Dispatch<SetStateAction<MediaShareType>>;
    setShareOpen: Dispatch<SetStateAction<boolean>>;
    can: (verb: string) => boolean;
    folderColors: string[];
    setFolderColor: Dispatch<SetStateAction<string>>;
    setDialog: Dispatch<
        SetStateAction<
            | "trash"
            | "folder"
            | "import"
            | "empty_trash"
            | "rename"
            | "alt_text"
            | "delete"
            | "crop"
            | "move"
            | "copy"
            | "properties"
            | null
        >
    >;
    download: () => Promise<void>;
    setText: Dispatch<SetStateAction<string>>;
    setRenamePhysicalFile: Dispatch<SetStateAction<boolean>>;
    action: (name: string, extra?: Record<string, unknown>) => Promise<void>;
    setDestination: Dispatch<SetStateAction<string>>;
    folder: string | number;
}) {
    const t = useAdminTranslation();
    return (
        <div className="flex flex-wrap items-center gap-2 border-b px-3 py-2">
            <label className="flex items-center gap-1 text-sm">
                <Checkbox
                    disabled={loading}
                    checked={all.length > 0 && selected.length === all.length}
                    indeterminate={
                        selected.length > 0 && selected.length < all.length
                    }
                    onCheckedChange={(checked) =>
                        setSelected(checked === true ? all : [])
                    }
                />
                {selected.length
                    ? `${selected.length} ${t("selected")}`
                    : t("Select all")}
            </label>
            {selected.length > 0 && (
                <DropdownMenu
                    label={t("Actions")}
                    trigger={
                        <span ref={actionsLabel} className="text-sm">
                            {t("Actions")}
                        </span>
                    }
                >
                    {view !== "trash" &&
                        selected.every((item) => !item.is_folder) && (
                            <>
                                {mediaShareText(selected, "url") && (
                                    <DropdownMenuItem
                                        disabled={busy}
                                        onClick={() =>
                                            void copyText(
                                                mediaShareText(selected, "url"),
                                            )
                                        }
                                    >
                                        {t("Copy link")}
                                    </DropdownMenuItem>
                                )}
                                {mediaShareText(selected, "indirect_url") && (
                                    <DropdownMenuItem
                                        disabled={busy}
                                        onClick={() =>
                                            void copyText(
                                                mediaShareText(
                                                    selected,
                                                    "indirect_url",
                                                ),
                                            )
                                        }
                                    >
                                        {t("Copy indirect link")}
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuItem
                                    disabled={busy}
                                    onClick={() => {
                                        shareReturnFocus.current =
                                            actionsLabel.current?.closest(
                                                "button",
                                            ) ?? null;
                                        setShareType("url");
                                        setShareOpen(true);
                                    }}
                                >
                                    {t("Share")}
                                </DropdownMenuItem>
                            </>
                        )}
                    {view !== "trash" &&
                        selected.every((item) => item.is_folder) &&
                        can("edit") &&
                        folderColors.length > 0 && (
                            <DropdownMenuItem
                                disabled={busy}
                                onClick={() => {
                                    setFolderColor(
                                        selected[0].color || folderColors[0],
                                    );
                                    setDialog("properties");
                                }}
                            >
                                {t("Folder color")}
                            </DropdownMenuItem>
                        )}
                    <DropdownMenuItem
                        disabled={busy}
                        onClick={() => void download()}
                    >
                        {t("Download")}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={busy}
                        onClick={() => {
                            setText(selected[0].name);
                            setRenamePhysicalFile(false);
                            setDialog("rename");
                        }}
                        hidden={selected.length !== 1 || !can("edit")}
                    >
                        {t("Rename")}
                    </DropdownMenuItem>
                    {view === "trash" ? (
                        <>
                            <DropdownMenuItem
                                disabled={busy || !can("edit")}
                                onClick={() => void action("restore")}
                            >
                                {t("Restore")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                destructive
                                disabled={busy || !can("destroy")}
                                onClick={() => setDialog("delete")}
                            >
                                {t("Delete permanently")}
                            </DropdownMenuItem>
                        </>
                    ) : (
                        <>
                            <DropdownMenuItem
                                disabled={busy || !can("favorite")}
                                onClick={() =>
                                    void action(
                                        view === "favorites"
                                            ? "remove_favorite"
                                            : "favorite",
                                    )
                                }
                            >
                                {t(
                                    view === "favorites"
                                        ? "Remove favorite"
                                        : "Favorite",
                                )}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={busy || !can("create")}
                                onClick={() => (
                                    setDestination(String(folder)),
                                    setDialog("copy")
                                )}
                            >
                                {t("Copy to folder")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={busy || !can("edit")}
                                onClick={() => {
                                    setDestination(String(folder));
                                    setDialog("move");
                                }}
                            >
                                {t("Move to folder")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={busy || !can("trash")}
                                onClick={() => setDialog("trash")}
                            >
                                {t("Move to trash")}
                            </DropdownMenuItem>
                            {selected.length === 1 &&
                                !selected[0].is_folder &&
                                can("edit") && (
                                    <>
                                        <DropdownMenuItem
                                            onClick={() => {
                                                setText(selected[0].alt ?? "");
                                                setDialog("alt_text");
                                            }}
                                        >
                                            {t("Alt text")}
                                        </DropdownMenuItem>
                                        {selected[0].mime_type?.startsWith(
                                            "image/",
                                        ) && (
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    setDialog("crop")
                                                }
                                            >
                                                {t("Crop image")}
                                            </DropdownMenuItem>
                                        )}
                                    </>
                                )}
                        </>
                    )}
                </DropdownMenu>
            )}
        </div>
    );
}
