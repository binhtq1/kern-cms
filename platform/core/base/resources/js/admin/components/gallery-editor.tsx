import { usePage } from "@inertiajs/react";
import type { SharedProps } from "@admin/types";
import { reorderGallery, type GalleryImage } from "./gallery-state";
import { useState, useRef, useEffect } from "react";
import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2 } from "lucide-react";
import { MediaPicker } from "./media-picker";
import { MediaLibraryDialog } from "./media-library-dialog";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Dialog } from "./ui/dialog";
import { Field } from "./field";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn } from "@admin/lib/utils";
export function GalleryEditor({
    images,
    onChange,
    listUrl: requestedListUrl,
    uploadUrl: requestedUploadUrl,
    disabled = false,
    error,
}: {
    images: GalleryImage[];
    onChange: (images: GalleryImage[]) => void;
    listUrl?: string;
    uploadUrl?: string;
    disabled?: boolean;
    error?: string;
}) {
    const t = useAdminTranslation();
    const { admin } = usePage<SharedProps>().props;
    const listUrl = admin.capabilities?.browseMedia
        ? requestedListUrl
        : undefined;
    const uploadUrl = admin.capabilities?.uploadMedia
        ? requestedUploadUrl
        : undefined;
    const clientKeys = useRef<number[]>([]);
    const nextKey = useRef(0);
    while (clientKeys.current.length < images.length)
        clientKeys.current.push(nextKey.current++);
    clientKeys.current.length = images.length;
    const handles = useRef(new Map<number, HTMLButtonElement>());
    const pendingFocus = useRef<number | null>(null);
    const registerHandle =
        (key: number) => (element: HTMLButtonElement | null) => {
            if (element) handles.current.set(key, element);
            else handles.current.delete(key);
        };
    useEffect(() => {
        if (pendingFocus.current !== null) {
            handles.current.get(pendingFocus.current)?.focus();
            pendingFocus.current = null;
        }
    }, [images]);
    const [picker, setPicker] = useState(false);
    const [reset, setReset] = useState(false);
    const [dragged, setDragged] = useState<number | null>(null);
    const [over, setOver] = useState<number | null>(null);
    const [announcement, setAnnouncement] = useState("");
    const move = (from: number, to: number) => {
        if (from === to || to < 0 || to >= images.length) return;
        const [key] = clientKeys.current.splice(from, 1);
        clientKeys.current.splice(to, 0, key);
        pendingFocus.current = key;
        onChange(reorderGallery(images, from, to));
        setAnnouncement(`${t("Image")} ${from + 1}: ${to + 1}`);
    };
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">
                    {t("Gallery")}{" "}
                    <span className="text-muted-foreground">
                        ({images.length})
                    </span>
                </h2>
                <div className="flex gap-2">
                    {!!images.length && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={disabled}
                            onClick={() => setReset(true)}
                        >
                            {t("Reset all")}
                        </Button>
                    )}
                    {listUrl && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={disabled}
                            onClick={() => setPicker(true)}
                        >
                            <Plus size={15} />
                            {t("Add images")}
                        </Button>
                    )}
                </div>
            </div>
            <div className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {images.map((image, index) => (
                    <div
                        key={clientKeys.current[index]}
                        className={cn(
                            "min-w-0 space-y-3 rounded-md border p-3",
                            dragged === index && "opacity-50",
                            over === index &&
                                dragged !== index &&
                                "border-primary ring-2 ring-primary/20",
                        )}
                        onDragOver={(event) => {
                            if (dragged !== null && !disabled) {
                                event.preventDefault();
                                event.dataTransfer.dropEffect = "move";
                                setOver(index);
                            }
                        }}
                        onDrop={(event) => {
                            if (dragged === null || disabled) return;
                            event.preventDefault();
                            move(dragged, index);
                            setDragged(null);
                            setOver(null);
                        }}
                    >
                        <div className="flex items-center justify-between gap-1">
                            <button
                                type="button"
                                ref={registerHandle(clientKeys.current[index])}
                                disabled={disabled}
                                draggable={!disabled}
                                aria-label={`${t("Drag to reorder")} ${index + 1}`}
                                className="flex cursor-grab items-center gap-1 rounded p-1 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring active:cursor-grabbing"
                                onDragStart={(event) => {
                                    event.dataTransfer.effectAllowed = "move";
                                    event.dataTransfer.setData(
                                        "text/plain",
                                        String(index),
                                    );
                                    setDragged(index);
                                }}
                                onDragEnd={() => {
                                    setDragged(null);
                                    setOver(null);
                                }}
                            >
                                <GripVertical size={16} />
                                <span className="text-xs">{index + 1}</span>
                            </button>
                            <div className="flex gap-1">
                                <Button
                                    type="button"
                                    size="icon"
                                    className="size-7"
                                    variant="ghost"
                                    disabled={disabled || index === 0}
                                    aria-label={t("Move up")}
                                    onClick={() => move(index, index - 1)}
                                >
                                    <ArrowUp size={14} />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    className="size-7"
                                    variant="ghost"
                                    disabled={
                                        disabled || index === images.length - 1
                                    }
                                    aria-label={t("Move down")}
                                    onClick={() => move(index, index + 1)}
                                >
                                    <ArrowDown size={14} />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    className="size-7 text-destructive"
                                    variant="ghost"
                                    disabled={disabled}
                                    aria-label={t("Remove image")}
                                    onClick={() => {
                                        clientKeys.current.splice(index, 1);
                                        onChange(
                                            images.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        );
                                    }}
                                >
                                    <Trash2 size={14} />
                                </Button>
                            </div>
                        </div>
                        <MediaPicker
                            value={image.img}
                            variant="thumbnail"
                            listUrl={disabled ? undefined : listUrl}
                            uploadUrl={disabled ? undefined : uploadUrl}
                            disabled={disabled}
                            onChange={(img) =>
                                onChange(
                                    images.map((item, position) =>
                                        position === index
                                            ? { ...item, img }
                                            : item,
                                    ),
                                )
                            }
                        />
                        <Field label={t("Description")}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={image.description ?? ""}
                                    disabled={disabled}
                                    onChange={(event) =>
                                        onChange(
                                            images.map((item, position) =>
                                                position === index
                                                    ? {
                                                          ...item,
                                                          description:
                                                              event.target
                                                                  .value,
                                                      }
                                                    : item,
                                            ),
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>
                ))}
            </div>
            {!images.length && (
                <p className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {t("No image selected")}
                </p>
            )}
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
            <p role="status" className="sr-only">
                {announcement}
            </p>
            {listUrl && (
                <MediaLibraryDialog
                    open={picker}
                    onOpenChange={setPicker}
                    listUrl={listUrl}
                    uploadUrl={uploadUrl}
                    imagesOnly
                    multiple
                    onInsert={(files) =>
                        onChange([
                            ...images,
                            ...files.map((file) => ({
                                img: file.url,
                                description: "",
                            })),
                        ])
                    }
                />
            )}
            <Dialog open={reset} onOpenChange={setReset} title={t("Reset all")}>
                <p className="text-sm text-muted-foreground">
                    {t("Remove all images from this gallery?")}
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setReset(false)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => {
                            onChange([]);
                            setReset(false);
                        }}
                    >
                        {t("Reset all")}
                    </Button>
                </div>
            </Dialog>
        </div>
    );
}
