import { FileUpload } from "@admin/components/file-upload";
import { UploadProgressPanel } from "@admin/components/upload-progress-panel";
import { notify } from "@admin/components/toast";
import { Button } from "@admin/components/ui/button";
import { Card } from "@admin/components/ui/card";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { copyToClipboard } from "@admin/lib/clipboard";
import { cn } from "@admin/lib/utils";
import axios from "axios";
import { ChevronRight } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { MediaActionDialog } from "../components/media-action-dialog";
import { MediaBrowser } from "../components/media-browser";
import { MediaDetails } from "../components/media-details";
import { MediaNavigation } from "../components/media-navigation-panel";
import { MediaPreviewDialog } from "../components/media-preview-dialog";
import { MediaSelectionActions } from "../components/media-selection-actions";
import { MediaShareDialog } from "../components/media-share-dialog";
import { MediaToolbar } from "../components/media-toolbar";
import { useMediaListing } from "../hooks/use-media-listing";
import { useMediaUploads } from "../hooks/use-media-uploads";
import type { Item, Props } from "../lib/media-types";
import { mediaDownloadFilename } from "./media-download";
import {
    defaultMediaNavigation,
    persistMediaNavigation,
    restoreMediaNavigation,
} from "./media-navigation";
import { rememberMedia } from "./media-recent";
import { type MediaShareType } from "./media-share";
export default function Index({
    urls,
    permissions,
    uploadOptions,
    folderColors,
    canEmptyTrash,
    navigationKey,
}: Props) {
    const t = useAdminTranslation();
    const navigationStorageKey = `kern-media-navigation:${navigationKey.split(":")[0]}`;
    const [initialNavigation] = useState(() => {
        try {
            return restoreMediaNavigation(
                localStorage.getItem(navigationStorageKey),
                navigationKey,
            );
        } catch {
            return { ...defaultMediaNavigation };
        }
    });
    const [recent, setRecent] = useState<string[]>(() => {
        try {
            return rememberMedia(
                JSON.parse(localStorage.getItem("kern-media-recent") ?? "[]"),
            );
        } catch {
            return [];
        }
    });
    function recordRecent(id: unknown) {
        setRecent((current) => {
            const next = rememberMedia(current, id);
            try {
                localStorage.setItem("kern-media-recent", JSON.stringify(next));
            } catch {}
            return next;
        });
    }
    const [view, setView] = useState(initialNavigation.view);
    const [folder, setFolder] = useState<string | number>(
        initialNavigation.folder,
    );
    const [search, setSearch] = useState("");
    const [filter, setFilter] = useState(initialNavigation.filter);
    const [sort, setSort] = useState(initialNavigation.sort);
    const [page, setPage] = useState(1);
    const [grid, setGrid] = useState(initialNavigation.grid);
    const [selected, setSelected] = useState<Item[]>([]);
    useEffect(() => {
        try {
            localStorage.setItem(
                navigationStorageKey,
                persistMediaNavigation(
                    { folder: String(folder), view, sort, filter, grid },
                    navigationKey,
                ),
            );
        } catch {
            /* Storage can be unavailable in private browser sessions. */
        }
    }, [folder, view, sort, filter, grid, navigationKey, navigationStorageKey]);
    const mediaContext = useRef(0);
    useEffect(
        () => () => {
            mediaContext.current += 1;
        },
        [view, folder, search, filter, sort, page],
    );
    const [busy, setBusy] = useState(false);
    const [renamePhysicalFile, setRenamePhysicalFile] = useState(false);
    const [destination, setDestination] = useState("0");
    const [destinations, setDestinations] = useState<
        { id: string | number; name: string; parent_id: string | number }[]
    >([]);
    const setNotice = (message: string) => notify(message);
    const selectionAnchor = useRef<string | null>(null);
    const [version, setVersion] = useState(0);
    const { result, loading, error, setError } = useMediaListing({
        url: urls.list,
        view,
        folder,
        search,
        filter,
        sort,
        page,
        version,
        recent,
        setFolder,
        setPage,
    });
    const {
        upload,
        retryUpload,
        uploadItems,
        dismissUpload,
        uploadController,
    } = useMediaUploads({
        urls,
        folder,
        uploadOptions,
        mediaContext,
        setBusy,
        setError,
        setVersion,
        recordRecent,
    });
    const [dialog, setDialog] = useState<
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
    >(null);
    const actionsLabel = useRef<HTMLSpanElement>(null);
    const shareReturnFocus = useRef<HTMLButtonElement>(null);
    const [shareOpen, setShareOpen] = useState(false);
    const [shareType, setShareType] = useState<MediaShareType>("url");
    async function copyText(value: string) {
        try {
            await copyToClipboard(value);
            notify(t("Copied"));
        } catch {
            notify(
                t("Could not copy. Select and copy the text manually."),
                true,
            );
        }
    }
    const [text, setText] = useState("");
    const [folderColor, setFolderColor] = useState(folderColors[0] ?? "");
    const [preview, setPreview] = useState<Item | null>(null);
    const [cropReady, setCropReady] = useState(false);
    const [crop, setCrop] = useState({ x: 0, y: 0, width: 100, height: 100 });
    const all = [
        ...result.folders.map((item) => ({ ...item, is_folder: true })),
        ...result.files.map((item) => ({ ...item, is_folder: false })),
    ];
    const key = (item: Item) =>
        `${item.is_folder ? "folder" : "file"}-${item.id}`;
    const can = (verb: string) =>
        selected.every((item) =>
            permissions.includes(
                `${item.is_folder ? "folders" : "files"}.${verb}`,
            ),
        );
    useEffect(() => {
        setSelected([]);
        selectionAnchor.current = null;
    }, [urls.list, view, folder, search, filter, sort, page, version]);
    useEffect(() => {
        setError("");
        if (dialog !== "rename") setRenamePhysicalFile(false);
    }, [dialog, setError]);
    async function action(name: string, extra: Record<string, unknown> = {}) {
        setBusy(true);
        setError("");
        try {
            const response = await axios.post(urls.actions, {
                action: name,
                selected: selected.map((item) => ({
                    id: String(item.id),
                    is_folder: item.is_folder,
                    name: text,
                    alt: text,
                    ...(name === "rename"
                        ? { rename_physical_file: renamePhysicalFile }
                        : {}),
                })),
                ...extra,
            });
            if (response.data.error) throw new Error(response.data.message);
            setNotice(response.data.message);
            setDialog(null);
            setVersion((value) => value + 1);
        } catch (reason) {
            const error = reason as {
                response?: { data?: { message?: string } };
                message: string;
            };
            setError(error.response?.data?.message ?? error.message);
        } finally {
            setBusy(false);
        }
    }
    useEffect(() => {
        if (dialog !== "move" && dialog !== "copy") return;
        const controller = new AbortController();
        axios
            .get(urls.folderPicker, { signal: controller.signal })
            .then((response) => setDestinations(response.data))
            .catch((reason) => {
                if (!axios.isCancel(reason)) setError(reason.message);
            });
        return () => controller.abort();
    }, [dialog, urls.folderPicker, setError]);

    async function download() {
        setBusy(true);
        setError("");
        try {
            const response = await axios.post(
                urls.download,
                {
                    selected: selected.map((item) => ({
                        id: String(item.id),
                        is_folder: item.is_folder,
                    })),
                },
                { responseType: "blob" },
            );
            if (String(response.headers["content-type"]).includes("json")) {
                const result = JSON.parse(await response.data.text());
                throw new Error(result.message ?? t("Download failed"));
            }
            const url = URL.createObjectURL(response.data);
            const link = document.createElement("a");
            link.href = url;
            link.download = mediaDownloadFilename(
                response.headers["content-disposition"],
                selected,
                response.headers["content-type"],
            );
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (reason) {
            setError((reason as Error).message);
        } finally {
            setBusy(false);
        }
    }
    function open(item: Item) {
        if (item.is_folder) {
            setFolder(item.id);
            setPage(1);
        } else {
            setPreview(item);
            recordRecent(item.id);
        }
    }
    const toggle = (item: Item) =>
        setSelected((current) =>
            current.some((value) => key(value) === key(item))
                ? current.filter((value) => key(value) !== key(item))
                : [...current, item],
        );
    return (
        <AdminLayout
            title={t("Media library")}
            actions={
                <div className="flex flex-wrap gap-2">
                    {permissions.includes("folders.create") &&
                        view !== "trash" && (
                            <Button
                                variant="outline"
                                disabled={busy || loading}
                                onClick={() => {
                                    setText("");
                                    setDialog("folder");
                                }}
                            >
                                {t("New folder")}
                            </Button>
                        )}
                    {permissions.includes("files.create") &&
                        view !== "trash" && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => {
                                        setText("");
                                        setError("");
                                        setDialog("import");
                                    }}
                                >
                                    {t("Upload from URL")}
                                </Button>
                                <FileUpload
                                    disabled={busy}
                                    multiple
                                    onFiles={(files) => void upload(files)}
                                />
                            </div>
                        )}
                    {view === "trash" && canEmptyTrash && all.length > 0 && (
                        <Button
                            variant="destructive"
                            disabled={busy || loading}
                            onClick={() => {
                                setError("");
                                setDialog("empty_trash");
                            }}
                        >
                            {t("Empty trash")}
                        </Button>
                    )}
                </div>
            }
        >
            <div className="space-y-4">
                <UploadProgressPanel
                    key={uploadItems[0]?.id}
                    items={uploadItems}
                    onCancel={() => uploadController.current?.abort()}
                    onDismiss={dismissUpload}
                    onRetry={(id) => void retryUpload(id)}
                    retryDisabled={busy}
                />
                {error && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}
                <div
                    className={cn(
                        "grid items-start gap-0 overflow-hidden rounded-md border bg-card lg:grid-cols-[11rem_minmax(0,1fr)]",
                        selected.length === 1 &&
                            "xl:grid-cols-[11rem_minmax(0,1fr)_16rem]",
                    )}
                >
                    <MediaNavigation
                        view={view}
                        setView={setView}
                        setFolder={setFolder}
                        setPage={setPage}
                        filter={filter}
                        setFilter={setFilter}
                    />
                    <Card className="min-w-0 rounded-none border-0 shadow-none">
                        <MediaToolbar
                            view={view}
                            setView={setView}
                            setFolder={setFolder}
                            setPage={setPage}
                            setSearch={setSearch}
                            search={search}
                            filter={filter}
                            setFilter={setFilter}
                            sort={sort}
                            setSort={setSort}
                            setVersion={setVersion}
                            loading={loading}
                            grid={grid}
                            setGrid={setGrid}
                        />
                        <div className="flex flex-wrap items-center gap-1 border-b px-3 py-2 text-sm">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setFolder(0);
                                    setPage(1);
                                }}
                            >
                                {t("Media")}
                            </Button>
                            {result.breadcrumbs
                                .filter((item) => String(item.id) !== "0")
                                .map((item) => (
                                    <span
                                        key={item.id}
                                        className="flex items-center"
                                    >
                                        <ChevronRight className="size-3 text-muted-foreground" />
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setFolder(item.id);
                                                setPage(1);
                                            }}
                                        >
                                            {item.name}
                                        </Button>
                                    </span>
                                ))}
                        </div>
                        <MediaSelectionActions
                            loading={loading}
                            all={all}
                            selected={selected}
                            setSelected={setSelected}
                            actionsLabel={actionsLabel}
                            view={view}
                            busy={busy}
                            copyText={copyText}
                            shareReturnFocus={shareReturnFocus}
                            setShareType={setShareType}
                            setShareOpen={setShareOpen}
                            can={can}
                            folderColors={folderColors}
                            setFolderColor={setFolderColor}
                            setDialog={setDialog}
                            download={download}
                            setText={setText}
                            setRenamePhysicalFile={setRenamePhysicalFile}
                            action={action}
                            setDestination={setDestination}
                            folder={folder}
                        />
                        <MediaBrowser
                            permissions={permissions}
                            view={view}
                            busy={busy}
                            upload={upload}
                            loading={loading}
                            all={all}
                            grid={grid}
                            selected={selected}
                            toggle={toggle}
                            open={open}
                            selectionAnchor={selectionAnchor}
                            setSelected={setSelected}
                        />
                        {(page > 1 || all.length >= 30) && (
                            <div className="flex justify-end gap-2 border-t p-3">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={page <= 1 || loading}
                                    onClick={() =>
                                        setPage((value) => value - 1)
                                    }
                                >
                                    {t("Previous")}
                                </Button>
                                <span className="self-center text-sm">
                                    {page}
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={all.length < 30 || loading}
                                    onClick={() =>
                                        setPage((value) => value + 1)
                                    }
                                >
                                    {t("Next")}
                                </Button>
                            </div>
                        )}
                    </Card>
                    {selected.length === 1 && (
                        <MediaDetails
                            setSelected={setSelected}
                            selected={selected}
                            view={view}
                            copyText={copyText}
                            open={open}
                        />
                    )}
                </div>
            </div>
            <MediaShareDialog
                shareOpen={shareOpen}
                setShareOpen={setShareOpen}
                shareReturnFocus={shareReturnFocus}
                shareType={shareType}
                setShareType={setShareType}
                selected={selected}
                copyText={copyText}
            />
            <MediaActionDialog
                dialog={dialog}
                busy={busy}
                setDialog={setDialog}
                setBusy={setBusy}
                setError={setError}
                mediaContext={mediaContext}
                urls={urls}
                text={text}
                folder={folder}
                recordRecent={recordRecent}
                setNotice={setNotice}
                setVersion={setVersion}
                action={action}
                folderColor={folderColor}
                selected={selected}
                destination={destination}
                crop={crop}
                error={error}
                setText={setText}
                setFolderColor={setFolderColor}
                folderColors={folderColors}
                renamePhysicalFile={renamePhysicalFile}
                setRenamePhysicalFile={setRenamePhysicalFile}
                setDestination={setDestination}
                destinations={destinations}
                setCrop={setCrop}
                setCropReady={setCropReady}
                cropReady={cropReady}
            />
            <MediaPreviewDialog
                preview={preview}
                onClose={() => setPreview(null)}
            />
        </AdminLayout>
    );
}
