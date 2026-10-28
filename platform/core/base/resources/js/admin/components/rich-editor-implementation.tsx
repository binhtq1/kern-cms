import { Button } from "@admin/components/ui/button";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import type { SharedProps } from "@admin/types";
import { usePage } from "@inertiajs/react";
import { type JSONContent } from "@tiptap/core";
import { EditorContent, useEditor } from "@tiptap/react";
import { Code, Maximize2, Minimize2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useEditorShortcode } from "../hooks/use-editor-shortcode";
import type { Shortcode } from "../lib/rich-editor-shortcodes";
import { useFieldControl } from "./field";
import { validImageSource } from "./image-source";
import { MediaLibraryDialog } from "./media-library-dialog";
import { createRichEditorExtensions } from "./rich-editor-extension-config";
import { visualEditingIssue } from "./rich-editor-safety";
import { RichEditorShortcodeDialog } from "./rich-editor-shortcode-dialog";
import { RichEditorToolbar } from "./rich-editor-toolbar";
import { UiBlocksDialog } from "./ui-blocks-dialog";
export type { Shortcode } from "../lib/rich-editor-shortcodes";
export function RichEditor({
    id,
    value,
    onChange,
    mediaUrl: requestedMediaUrl,
    uploadUrl: requestedUploadUrl,
    shortcodes = [],
}: {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    mediaUrl?: string;
    uploadUrl?: string;
    shortcodes?: Shortcode[];
}) {
    const t = useAdminTranslation();
    const { admin } = usePage<SharedProps>().props;
    const mediaUrl = admin.capabilities?.browseMedia
        ? requestedMediaUrl
        : undefined;
    const uploadUrl = admin.capabilities?.uploadMedia
        ? requestedUploadUrl
        : undefined;
    const fieldControl = useFieldControl(id);
    const [source, setSource] = useState(false);
    const [fullscreen, setFullscreen] = useState(false);
    useEffect(() => {
        if (!fullscreen) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        const escape = (event: KeyboardEvent) => {
            if (
                event.key === "Escape" &&
                !document.querySelector('[role="dialog"]')
            )
                setFullscreen(false);
        };
        document.addEventListener("keydown", escape);
        return () => {
            document.body.style.overflow = previous;
            document.removeEventListener("keydown", escape);
        };
    }, [fullscreen]);
    const [blocksOpen, setBlocksOpen] = useState(false);
    const shortcodeReturnFocus = useRef<HTMLElement | null>(null);
    const sourceControl = useRef<HTMLTextAreaElement | null>(null);
    const [visualIssue, setVisualIssue] = useState("");
    const [image, setImage] = useState("");
    const [imagesOpen, setImagesOpen] = useState(false);
    const [imageError, setImageError] = useState("");
    const [imageBusy, setImageBusy] = useState(false);
    const shortcodeState = useEditorShortcode(value);
    const { editingCode, closeShortcode, existing, configure, generatedCode } =
        shortcodeState;
    function insertShortcode() {
        const code = generatedCode();
        if (editingCode) {
            onChange(value.replace(editingCode, () => code));
        } else if (source) {
            onChange(value + "\n" + code);
        } else {
            editor
                ?.chain()
                .focus()
                .insertContent({ type: "text", text: code })
                .run();
        }
        shortcodeReturnFocus.current = source
            ? sourceControl.current
            : (editor?.view.dom ?? shortcodeReturnFocus.current);
        closeShortcode();
    }
    const editor = useEditor({
        extensions: createRichEditorExtensions(),
        content: value,
        editorProps: {
            attributes: {
                ...(id
                    ? { id, "aria-labelledby": `${id}-label` }
                    : { "aria-label": t("Content") }),
                role: "textbox",
                "aria-multiline": "true",
                "aria-describedby": fieldControl["aria-describedby"] ?? "",
                "aria-invalid": fieldControl["aria-invalid"] ? "true" : "false",
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
        immediatelyRender: false,
        shouldRerenderOnTransaction: true,
    });
    function insertImages(images: JSONContent[]) {
        if (!editor || !images.length) return;
        editor
            .chain()
            .focus()
            .insertContent(images)
            .command(({ tr, commands }) => {
                const end = tr.selection.to;
                if (tr.doc.resolve(end).parent.isTextblock)
                    return commands.setTextSelection(end);
                tr.insert(end, editor.schema.nodes.paragraph.create());
                return commands.setTextSelection(end + 1);
            })
            .run();
    }
    useEffect(() => {
        if (editor && editor.getHTML() !== value && !editor.isFocused) {
            editor.commands.setContent(value, { emitUpdate: false });
            if (!source) {
                const issue = visualEditingIssue(value, editor.getHTML());
                if (issue) {
                    setSource(true);
                    setVisualIssue(
                        `${t("Visual editing is unavailable because it would change unsupported HTML:")} ${issue}. ${t("Continue editing in HTML source to preserve this content.")}`,
                    );
                }
            }
        }
    }, [value, editor, source, t]);
    return (
        <div
            className={
                fullscreen
                    ? "fixed inset-0 z-40 overflow-y-auto bg-card"
                    : "overflow-hidden rounded-lg border bg-card"
            }
            data-invalid={fieldControl["aria-invalid"] || undefined}
        >
            <div
                className={`flex flex-wrap items-center gap-1 border-b bg-card p-2 ${fullscreen ? "sticky top-0 z-10" : ""}`}
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!editor}
                    onClick={() => {
                        setVisualIssue("");
                        if (source && editor) {
                            editor.commands.setContent(value, {
                                emitUpdate: false,
                            });
                            const issue = visualEditingIssue(
                                value,
                                editor.getHTML(),
                            );
                            if (issue) {
                                setVisualIssue(
                                    `${t("Visual editing is unavailable because it would change unsupported HTML:")} ${issue}. ${t("Continue editing in HTML source to preserve this content.")}`,
                                );
                                return;
                            }
                        }
                        setSource(!source);
                    }}
                >
                    <Code size={15} />
                    {source ? t("Visual editor") : t("HTML source")}
                </Button>
                {!source && editor && <RichEditorToolbar editor={editor} />}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t(
                        fullscreen ? "Exit fullscreen" : "Fullscreen",
                    )}
                    title={t(fullscreen ? "Exit fullscreen" : "Fullscreen")}
                    aria-pressed={fullscreen}
                    onClick={() => setFullscreen(!fullscreen)}
                >
                    {fullscreen ? <Minimize2 /> : <Maximize2 />}
                </Button>
                {shortcodes.length > 0 && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="sm:ml-auto"
                        onClick={(event) => {
                            shortcodeReturnFocus.current = event.currentTarget;
                            setBlocksOpen(true);
                        }}
                    >
                        {t("UI Blocks")}
                    </Button>
                )}
                {shortcodes.length > 0 && existing.length > 0 && (
                    <Select
                        aria-label={t("Edit shortcode")}
                        className="sm:h-9 sm:text-xs"
                        value=""
                        onFocus={(event) => {
                            shortcodeReturnFocus.current = event.currentTarget;
                        }}
                        onValueChange={(value) => {
                            const code = existing[Number(value)];
                            const key = code?.match(/^\[([\w-]+)/)?.[1];
                            const item = shortcodes.find(
                                (item) => item.key === key,
                            );
                            if (item) {
                                configure(item, code);
                            }
                        }}
                    >
                        <option value="">{t("Edit shortcode")}</option>
                        {existing.map((code, index) => (
                            <option key={index} value={index}>
                                {code.slice(0, 70)}
                            </option>
                        ))}
                    </Select>
                )}
            </div>
            <UiBlocksDialog
                open={blocksOpen}
                onOpenChange={setBlocksOpen}
                blocks={shortcodes}
                onUse={(item) => {
                    setBlocksOpen(false);
                    configure(item);
                }}
            />
            <RichEditorShortcodeDialog
                state={shortcodeState}
                shortcodeReturnFocus={shortcodeReturnFocus}
                mediaUrl={mediaUrl}
                uploadUrl={uploadUrl}
                insertShortcode={insertShortcode}
            />
            {visualIssue && (
                <p
                    role="alert"
                    className="border-b bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    {visualIssue}
                </p>
            )}
            {source ? (
                <Textarea
                    ref={sourceControl}
                    id={id}
                    aria-label={id ? undefined : t("HTML content")}
                    className="min-h-88 rounded-none border-0 font-mono text-sm"
                    value={value}
                    onChange={(event) => {
                        setVisualIssue("");
                        onChange(event.target.value);
                    }}
                />
            ) : (
                <EditorContent editor={editor} />
            )}
            {!source && (
                <div className="space-y-2 border-t p-3">
                    <div className="flex flex-wrap gap-2">
                        {mediaUrl && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-full sm:w-auto"
                                onClick={() => setImagesOpen(true)}
                            >
                                {t("Choose images")}
                            </Button>
                        )}
                        <Input
                            value={image}
                            onChange={(event) => {
                                setImage(event.target.value);
                                setImageError("");
                            }}
                            placeholder={t("Image path or URL")}
                            aria-label={t("Image path or URL")}
                            className="min-w-0 flex-1"
                        />
                        <Button
                            type="button"
                            size="sm"
                            disabled={!image.trim() || imageBusy}
                            onClick={async () => {
                                setImageBusy(true);
                                setImageError("");
                                try {
                                    let src = validImageSource(image);
                                    if (
                                        !src &&
                                        mediaUrl &&
                                        !/^[a-z][a-z0-9+.-]*:/i.test(image) &&
                                        !image.startsWith("//")
                                    ) {
                                        const url = new URL(
                                            mediaUrl,
                                            window.location.origin,
                                        );
                                        url.searchParams.set(
                                            "path",
                                            image.trim(),
                                        );
                                        url.searchParams.set("type", "image");
                                        const response = await fetch(url, {
                                            headers: {
                                                Accept: "application/json",
                                            },
                                        });
                                        const result = response.ok
                                            ? await response.json()
                                            : null;
                                        const file = result?.data?.find(
                                            (file: { url: string }) =>
                                                file.url === image.trim(),
                                        );
                                        src = file
                                            ? validImageSource(file.src)
                                            : null;
                                    }
                                    if (!src)
                                        throw new Error(
                                            t(
                                                "Enter a valid image URL or choose an image from the library.",
                                            ),
                                        );
                                    insertImages([
                                        { type: "image", attrs: { src } },
                                    ]);
                                    setImage("");
                                } catch (reason) {
                                    setImageError(
                                        reason instanceof Error
                                            ? reason.message
                                            : t("Something went wrong"),
                                    );
                                } finally {
                                    setImageBusy(false);
                                }
                            }}
                        >
                            {t("Insert image")}
                        </Button>
                    </div>
                    {imageError && (
                        <p role="alert" className="text-xs text-destructive">
                            {imageError}
                        </p>
                    )}
                    {mediaUrl && (
                        <MediaLibraryDialog
                            open={imagesOpen}
                            onOpenChange={setImagesOpen}
                            listUrl={mediaUrl}
                            uploadUrl={uploadUrl}
                            imagesOnly
                            multiple
                            onInsert={(files) => {
                                const images = files.flatMap((file) => {
                                    const src = validImageSource(file.src);
                                    return src
                                        ? [
                                              {
                                                  type: "image",
                                                  attrs: {
                                                      src,
                                                      alt: file.name,
                                                  },
                                              },
                                          ]
                                        : [];
                                });
                                insertImages(images);
                            }}
                        />
                    )}
                </div>
            )}
            <p className="border-t px-3 py-2 text-xs text-muted-foreground">
                {t(
                    "HTML source preserves existing layouts and shortcode attributes. Visual editing supports standard rich text.",
                )}
            </p>
        </div>
    );
}
