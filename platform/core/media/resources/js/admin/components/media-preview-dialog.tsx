import { Button } from "@admin/components/ui/button";
import { Dialog } from "@admin/components/ui/dialog";
import { useAdminDate } from "@admin/lib/date";
import { useAdminTranslation } from "@admin/lib/translate";
import { mediaDocumentPreview } from "../Media/media-document-preview";
import type { Item } from "../lib/media-types";
export function MediaPreviewDialog({
    preview,
    onClose,
}: {
    preview: Item | null;
    onClose: () => void;
}) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();
    const setPreview = (_: null) => onClose();
    const documentPreview = preview
        ? mediaDocumentPreview(preview, window.location.origin)
        : null;
    return (
        <Dialog
            size="xl"
            open={!!preview}
            onOpenChange={(open) => !open && setPreview(null)}
            title={preview?.name ?? t("File details")}
        >
            <div className="space-y-4">
                {preview?.mime_type?.startsWith("image/") && (
                    <img
                        src={
                            preview.preview_url ??
                            preview.full_url ??
                            preview.indirect_url ??
                            ""
                        }
                        alt={preview.alt ?? ""}
                        className="max-h-[70vh] w-full object-contain"
                    />
                )}
                {preview?.mime_type?.startsWith("video/") && (
                    <video
                        controls
                        preload="metadata"
                        src={preview.full_url ?? preview.indirect_url}
                        className="max-h-[65vh] w-full"
                    />
                )}
                {preview?.mime_type?.startsWith("audio/") && (
                    <audio
                        controls
                        preload="metadata"
                        src={preview.full_url ?? preview.indirect_url}
                        className="w-full"
                    />
                )}
                {documentPreview &&
                    (documentPreview.popup ? (
                        <Button asChild variant="outline">
                            <a
                                href={documentPreview.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t("Open preview")}
                            </a>
                        </Button>
                    ) : (
                        <iframe
                            src={documentPreview.url}
                            title={preview?.name ?? t("Document preview")}
                            className="h-[65vh] w-full rounded-md border"
                            referrerPolicy="no-referrer"
                            loading="lazy"
                        />
                    ))}
                <dl className="grid grid-cols-[auto_1fr] gap-x-5 gap-y-2 text-sm">
                    <dt>{t("File type")}</dt>
                    <dd>{preview?.mime_type}</dd>
                    <dt>{t("Size")}</dt>
                    <dd>{preview?.size}</dd>
                    <dt>{t("Created")}</dt>
                    <dd>{formatDate(preview?.created_at, true)}</dd>
                    <dt>{t("Alt text")}</dt>
                    <dd>{preview?.alt || "—"}</dd>
                </dl>
                {preview && (preview.full_url || preview.indirect_url) && (
                    <Button asChild variant="outline">
                        <a
                            href={preview.full_url ?? preview.indirect_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t("Open file")}
                        </a>
                    </Button>
                )}
            </div>
        </Dialog>
    );
}
