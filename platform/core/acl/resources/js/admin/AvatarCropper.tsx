import { useEffect, useRef, useState } from "react";
import Cropper from "cropperjs";
import "cropperjs/dist/cropper.css";
import { Dialog } from "@admin/components/ui/dialog";
import { Button } from "@admin/components/ui/button";
import { useAdminTranslation } from "@admin/lib/translate";

export function AvatarCropper({
    file,
    onClose,
    onApply,
}: {
    file: File;
    onClose: () => void;
    onApply: (file: File) => void;
}) {
    const t = useAdminTranslation();
    const image = useRef<HTMLImageElement>(null);
    const instance = useRef<Cropper | null>(null);
    const [source, setSource] = useState("");
    const [ready, setReady] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        const url = URL.createObjectURL(file);
        setSource(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    useEffect(() => {
        if (!source || !image.current) return;
        setReady(false);
        const cropper = new Cropper(image.current, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: "move",
            autoCropArea: 0.9,
            background: false,
            rotatable: false,
            scalable: false,
            ready: () => setReady(true),
        });
        instance.current = cropper;
        return () => {
            cropper.destroy();
            instance.current = null;
        };
    }, [source]);
    function apply() {
        const canvas = instance.current?.getCroppedCanvas({
            width: 512,
            height: 512,
            maxWidth: 512,
            maxHeight: 512,
            imageSmoothingQuality: "high",
        });
        if (!canvas) return;
        setBusy(true);
        canvas.toBlob((blob) => {
            setBusy(false);
            if (!blob) {
                setError(t("Could not crop image"));
                return;
            }
            onApply(
                new File(
                    [blob],
                    `${file.name.replace(/\.[^.]+$/, "")}-avatar.png`,
                    { type: "image/png" },
                ),
            );
        }, "image/png");
    }
    return (
        <Dialog
            open
            onOpenChange={(open) => !open && !busy && onClose()}
            title={t("Crop avatar")}
        >
            <p className="my-3 text-sm text-muted-foreground">
                {t(
                    "Drag to position your image or use the adjustment buttons.",
                )}
            </p>
            <div className="h-80 overflow-hidden rounded-md border bg-muted">
                <img
                    ref={image}
                    src={source || undefined}
                    alt={t("Crop preview")}
                    className="max-w-full"
                    onError={() => setError(t("Image preview unavailable"))}
                />
            </div>
            {error && (
                <p role="alert" className="mt-3 text-sm text-destructive">
                    {error}
                </p>
            )}
            <div className="mt-3 flex flex-wrap gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!ready || busy}
                    onClick={() => instance.current?.zoom(0.1)}
                >
                    {t("Zoom in")}
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!ready || busy}
                    onClick={() => instance.current?.zoom(-0.1)}
                >
                    {t("Zoom out")}
                </Button>
                {(
                    [
                        [-10, 0, "Move left"],
                        [10, 0, "Move right"],
                        [0, -10, "Move up"],
                        [0, 10, "Move down"],
                    ] as const
                ).map(([x, y, label]) => (
                    <Button
                        key={label}
                        size="sm"
                        variant="outline"
                        disabled={!ready || busy}
                        onClick={() => instance.current?.move(x, y)}
                    >
                        {t(label)}
                    </Button>
                ))}
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={!ready || busy}
                    onClick={() => instance.current?.reset()}
                >
                    {t("Reset")}
                </Button>
            </div>
            <div className="mt-5 flex justify-end gap-2">
                <Button variant="outline" disabled={busy} onClick={onClose}>
                    {t("Cancel")}
                </Button>
                <Button
                    disabled={!ready || !!error}
                    loading={busy}
                    onClick={apply}
                >
                    {t("Apply crop")}
                </Button>
            </div>
        </Dialog>
    );
}
