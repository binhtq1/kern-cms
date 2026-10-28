import { useEffect, useRef, useState } from "react";
import Cropper from "cropperjs";
import "cropperjs/dist/cropper.css";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { Field } from "@admin/components/field";
import { useAdminTranslation } from "@admin/lib/translate";

type Crop = { x: number; y: number; width: number; height: number };

export function ImageCropper({
    src,
    value,
    onChange,
    onReadyChange,
}: {
    src: string;
    value: Crop;
    onChange: (crop: Crop) => void;
    onReadyChange: (ready: boolean) => void;
}) {
    const t = useAdminTranslation();
    const image = useRef<HTMLImageElement>(null);
    const instance = useRef<Cropper | null>(null);
    const onCrop = useRef(onChange);
    onCrop.current = onChange;
    const onReady = useRef(onReadyChange);
    onReady.current = onReadyChange;
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        if (!image.current) return;
        setFailed(false);
        onReady.current(false);
        const cropper = new Cropper(image.current, {
            viewMode: 1,
            autoCropArea: 0.8,
            background: false,
            rotatable: false,
            scalable: false,
            zoomable: false,
            checkCrossOrigin: false,
            ready() {
                onReady.current(true);
            },
            crop(event) {
                const { x, y, width, height } = event.detail;
                onCrop.current({
                    x: Math.round(x),
                    y: Math.round(y),
                    width: Math.round(width),
                    height: Math.round(height),
                });
            },
        });
        instance.current = cropper;
        return () => {
            onReady.current(false);
            cropper.destroy();
            instance.current = null;
        };
    }, [src]);
    return (
        <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
                {t(
                    "Drag the crop area or enter coordinates in pixels. This replaces the original image.",
                )}
            </p>
            <div className="h-72 overflow-hidden rounded-md border bg-muted/30">
                <img
                    ref={image}
                    src={src}
                    alt={t("Crop preview")}
                    className="max-w-full"
                    onError={() => setFailed(true)}
                />
            </div>
            {failed && (
                <p role="alert" className="text-sm text-destructive">
                    {t("Image preview unavailable")}
                </p>
            )}
            <Button
                size="sm"
                variant="outline"
                onClick={() => instance.current?.reset()}
            >
                {t("Reset")}
            </Button>
            <div className="grid grid-cols-2 gap-3">
                {(["x", "y", "width", "height"] as const).map((key) => (
                    <Field
                        key={key}
                        label={t(
                            key === "x"
                                ? "Left"
                                : key === "y"
                                  ? "Top"
                                  : key === "width"
                                    ? "Width"
                                    : "Height",
                        )}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                min={
                                    key === "width" || key === "height" ? 1 : 0
                                }
                                required
                                value={value[key]}
                                onChange={(event) => {
                                    const next = {
                                        ...value,
                                        [key]: Number(event.target.value),
                                    };
                                    onChange(next);
                                    instance.current?.setData(next);
                                }}
                            />
                        )}
                    </Field>
                ))}
            </div>
        </div>
    );
}
