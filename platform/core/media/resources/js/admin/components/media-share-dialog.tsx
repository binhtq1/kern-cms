import { Field } from "@admin/components/field";
import { Button } from "@admin/components/ui/button";
import { Dialog } from "@admin/components/ui/dialog";
import { Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { Copy } from "lucide-react";
import type { RefObject } from "react";
import { mediaShareText, type MediaShareType } from "../Media/media-share";
import type { Item } from "../lib/media-types";
export function MediaShareDialog({
    shareOpen,
    setShareOpen,
    shareReturnFocus,
    shareType,
    setShareType,
    selected,
    copyText,
}: {
    shareOpen: boolean;
    setShareOpen: (value: boolean) => void;
    shareReturnFocus: RefObject<HTMLButtonElement | null>;
    shareType: MediaShareType;
    setShareType: (value: MediaShareType) => void;
    selected: Item[];
    copyText: (value: string) => Promise<void>;
}) {
    const t = useAdminTranslation();
    const shareText = mediaShareText(selected, shareType);
    return (
        <Dialog
            open={shareOpen}
            returnFocusRef={shareReturnFocus}
            onOpenChange={setShareOpen}
            title={t("Share")}
        >
            <div className="space-y-4">
                <Field label={t("Share type")}>
                    {(id) => (
                        <Select
                            id={id}
                            aria-label={t("Share type")}
                            value={shareType}
                            onValueChange={(value) =>
                                setShareType(value as MediaShareType)
                            }
                        >
                            <option value="url">{t("URL")}</option>
                            <option value="indirect_url">
                                {t("Indirect URL")}
                            </option>
                            <option value="html">HTML</option>
                            <option value="markdown">Markdown</option>
                        </Select>
                    )}
                </Field>
                <Field label={t("Share results")}>
                    {(id) => (
                        <Textarea
                            id={id}
                            aria-label={t("Share results")}
                            value={shareText}
                            readOnly
                            className="min-h-40 font-mono"
                            onFocus={(event) => event.currentTarget.select()}
                        />
                    )}
                </Field>
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        onClick={() => setShareOpen(false)}
                    >
                        {t("Close")}
                    </Button>
                    <Button
                        disabled={!shareText}
                        onClick={() => void copyText(shareText)}
                    >
                        <Copy size={15} />
                        {t("Copy")}
                    </Button>
                </div>
            </div>
        </Dialog>
    );
}
