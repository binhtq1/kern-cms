import { Field } from "@admin/components/field";
import { Button } from "@admin/components/ui/button";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Dialog } from "@admin/components/ui/dialog";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import axios from "axios";
import type { Dispatch, RefObject, SetStateAction } from "react";
import type { Item, Props } from "../lib/media-types";
import { ImageCropper } from "../Media/image-cropper";
export function MediaActionDialog({
    dialog,
    busy,
    setDialog,
    setBusy,
    setError,
    mediaContext,
    urls,
    text,
    folder,
    recordRecent,
    setNotice,
    setVersion,
    action,
    folderColor,
    selected,
    destination,
    crop,
    error,
    setText,
    setFolderColor,
    folderColors,
    renamePhysicalFile,
    setRenamePhysicalFile,
    setDestination,
    destinations,
    setCrop,
    setCropReady,
    cropReady,
}: {
    dialog:
        | "folder"
        | "import"
        | "empty_trash"
        | "rename"
        | "alt_text"
        | "trash"
        | "delete"
        | "crop"
        | "move"
        | "copy"
        | "properties"
        | null;
    busy: boolean;
    setDialog: Dispatch<
        SetStateAction<
            | "folder"
            | "import"
            | "empty_trash"
            | "rename"
            | "alt_text"
            | "trash"
            | "delete"
            | "crop"
            | "move"
            | "copy"
            | "properties"
            | null
        >
    >;
    setBusy: Dispatch<SetStateAction<boolean>>;
    setError: Dispatch<SetStateAction<string>>;
    mediaContext: RefObject<number>;
    urls: Props["urls"];
    text: string;
    folder: string | number;
    recordRecent: (id: unknown) => void;
    setNotice: (message: string) => void;
    setVersion: Dispatch<SetStateAction<number>>;
    action: (name: string, extra?: Record<string, unknown>) => Promise<void>;
    folderColor: string;
    selected: Item[];
    destination: string;
    crop: { x: number; y: number; width: number; height: number };
    error: string;
    setText: Dispatch<SetStateAction<string>>;
    setFolderColor: Dispatch<SetStateAction<string>>;
    folderColors: string[];
    renamePhysicalFile: boolean;
    setRenamePhysicalFile: Dispatch<SetStateAction<boolean>>;
    setDestination: Dispatch<SetStateAction<string>>;
    destinations: {
        id: string | number;
        name: string;
        parent_id: string | number;
    }[];
    setCrop: Dispatch<
        SetStateAction<{ x: number; y: number; width: number; height: number }>
    >;
    setCropReady: Dispatch<SetStateAction<boolean>>;
    cropReady: boolean;
}) {
    const t = useAdminTranslation();
    return (
        <Dialog
            open={!!dialog}
            onOpenChange={(open) => !open && !busy && setDialog(null)}
            title={t(
                dialog === "empty_trash"
                    ? "Empty trash"
                    : dialog === "import"
                      ? "Upload from URL"
                      : dialog === "properties"
                        ? "Folder color"
                        : dialog === "folder"
                          ? "New folder"
                          : dialog === "rename"
                            ? "Rename"
                            : dialog === "alt_text"
                              ? "Alt text"
                              : dialog === "crop"
                                ? "Crop image"
                                : dialog === "move"
                                  ? "Move to folder"
                                  : dialog === "copy"
                                    ? "Copy to folder"
                                    : dialog === "delete"
                                      ? "Delete permanently"
                                      : "Move to trash",
            )}
        >
            <form
                onSubmit={async (event) => {
                    event.preventDefault();
                    if (dialog === "import") {
                        setBusy(true);
                        setError("");
                        const context = mediaContext.current;
                        try {
                            const response = await axios.post(urls.importUrl, {
                                url: text,
                                folderId: folder,
                            });
                            if (response.data.error)
                                throw new Error(response.data.message);
                            if (mediaContext.current === context)
                                recordRecent(response.data.data?.id);
                            setNotice(t("Upload complete"));
                            setDialog(null);
                            setVersion((value) => value + 1);
                        } catch (reason) {
                            setError(
                                axios.isAxiosError(reason)
                                    ? (reason.response?.data?.message ??
                                          reason.message)
                                    : (reason as Error).message,
                            );
                        } finally {
                            setBusy(false);
                        }
                    } else if (dialog === "folder") {
                        setBusy(true);
                        try {
                            const response = await axios.post(urls.folder, {
                                name: text,
                                parent_id: folder,
                            });
                            if (response.data.error)
                                throw new Error(response.data.message);
                            setNotice(
                                response.data.message || t("Folder created"),
                            );
                            setDialog(null);
                            setVersion((value) => value + 1);
                        } catch (reason) {
                            setError((reason as Error).message);
                        } finally {
                            setBusy(false);
                        }
                    } else if (dialog === "properties") {
                        void action("properties", {
                            color: folderColor,
                            selected: selected.map((item) => String(item.id)),
                        });
                    } else if (dialog === "move" || dialog === "copy") {
                        void action(dialog, {
                            destination_id: Number(destination),
                        });
                    } else if (dialog === "crop") {
                        void action("crop", {
                            imageId: String(selected[0]?.id),
                            cropData: JSON.stringify(crop),
                        });
                    } else if (dialog) {
                        void action(dialog);
                    }
                }}
                className="space-y-4"
            >
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {dialog === "import" && (
                    <Field label={t("File URL")}>
                        {(id) => (
                            <Input
                                id={id}
                                type="url"
                                autoFocus
                                required
                                value={text}
                                onChange={(event) =>
                                    setText(event.target.value)
                                }
                                placeholder="https://"
                            />
                        )}
                    </Field>
                )}
                {dialog === "properties" && (
                    <Field label={t("Folder color")}>
                        {(id) => (
                            <div className="flex items-center gap-3">
                                <span
                                    aria-hidden="true"
                                    className="size-7 rounded border"
                                    style={{ backgroundColor: folderColor }}
                                />
                                <Select
                                    id={id}
                                    value={folderColor}
                                    onValueChange={(value) =>
                                        setFolderColor(value)
                                    }
                                >
                                    {folderColors.map((color) => (
                                        <option key={color} value={color}>
                                            {color}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        )}
                    </Field>
                )}
                {["folder", "rename", "alt_text"].includes(dialog ?? "") && (
                    <Field
                        label={t(dialog === "alt_text" ? "Alt text" : "Name")}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                autoFocus
                                required={dialog !== "alt_text"}
                                maxLength={dialog === "alt_text" ? 220 : 120}
                                value={text}
                                onChange={(event) =>
                                    setText(event.target.value)
                                }
                            />
                        )}
                    </Field>
                )}
                {dialog === "rename" && (
                    <div className="space-y-3">
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={renamePhysicalFile}
                                onCheckedChange={(checked) =>
                                    setRenamePhysicalFile(checked === true)
                                }
                                disabled={busy}
                            />
                            {t(
                                selected[0]?.is_folder
                                    ? "Rename physical folder name on disk too"
                                    : "Rename physical file name on disk too",
                            )}
                        </label>
                        {renamePhysicalFile && (
                            <p
                                role="alert"
                                className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200"
                            >
                                {t(
                                    "Renaming on disk changes existing URLs. Links to this file or files inside this folder may stop working in pages, posts, and other places.",
                                )}
                            </p>
                        )}
                    </div>
                )}
                {(dialog === "move" || dialog === "copy") && (
                    <Field label={t("Destination folder")}>
                        {(id) => (
                            <Select
                                id={id}
                                aria-label={t("Destination folder")}
                                value={destination}
                                onValueChange={(value) => setDestination(value)}
                            >
                                <option value="0">{t("Root folder")}</option>
                                {destinations.map((item) => (
                                    <option
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name} (#{item.id})
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>
                )}
                {dialog === "crop" && selected[0] && (
                    <ImageCropper
                        src={
                            selected[0].full_url ??
                            selected[0].indirect_url ??
                            ""
                        }
                        value={crop}
                        onChange={setCrop}
                        onReadyChange={setCropReady}
                    />
                )}
                {(dialog === "delete" || dialog === "empty_trash") && (
                    <p className="text-sm text-destructive">
                        {t(
                            dialog === "empty_trash"
                                ? "All files and folders in Trash will be permanently deleted. This action cannot be undone."
                                : "This action cannot be undone.",
                        )}
                    </p>
                )}
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setDialog(null)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        type="submit"
                        loading={busy}
                        disabled={dialog === "crop" && !cropReady}
                        variant={
                            dialog === "delete" || dialog === "empty_trash"
                                ? "destructive"
                                : "default"
                        }
                    >
                        {t("Confirm")}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
