import { Button } from "@admin/components/ui/button";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Input, Textarea } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { ColorPicker } from "./color-picker";
import { Field } from "./field";
import { MediaPicker } from "./media-picker";
import { Dialog } from "./ui/dialog";

import type { RefObject } from "react";
import type { useEditorShortcode } from "../hooks/use-editor-shortcode";
export function RichEditorShortcodeDialog({
    state,
    shortcodeReturnFocus,
    mediaUrl,
    uploadUrl,
    insertShortcode,
}: {
    state: ReturnType<typeof useEditorShortcode>;
    shortcodeReturnFocus: RefObject<HTMLElement | null>;
    mediaUrl?: string;
    uploadUrl?: string;
    insertShortcode: () => void;
}) {
    const t = useAdminTranslation();
    const {
        shortcode,
        attributes,
        setAttributes,
        shortcodeContent,
        setShortcodeContent,
        editingCode,
        preview,
        previewError,
        previewLoading,
        resetPreview,
        closeShortcode,
        hasInvalidColor,
        showFallbackContent,
        generatedCode,
        previewShortcode,
    } = state;
    return (
        <Dialog
            open={!!shortcode}
            returnFocusRef={shortcodeReturnFocus}
            onOpenChange={(open) => {
                if (!open) {
                    closeShortcode();
                }
            }}
            title={shortcode?.name ?? t("Shortcode")}
            description={t(
                "Configure the content block, preview it, then insert it into the page.",
            )}
            footer={
                <>
                    {shortcode?.previewUrl && (
                        <Button
                            variant="outline"
                            loading={previewLoading}
                            disabled={hasInvalidColor}
                            onClick={() => void previewShortcode()}
                        >
                            {t("Preview")}
                        </Button>
                    )}
                    <Button variant="outline" onClick={closeShortcode}>
                        {t("Cancel")}
                    </Button>
                    <Button
                        disabled={hasInvalidColor}
                        onClick={insertShortcode}
                    >
                        {editingCode
                            ? t("Update shortcode")
                            : t("Insert shortcode")}
                    </Button>
                </>
            }
        >
            <div className="space-y-5">
                {shortcode?.fields?.map((field) => {
                    const current =
                        field.name === "content"
                            ? shortcodeContent
                            : (attributes[field.name] ?? "");
                    const text = Array.isArray(current)
                        ? current.join(",")
                        : current;
                    const change = (next: string | string[]) => {
                        if (field.name === "content") {
                            setShortcodeContent(
                                Array.isArray(next) ? next.join(",") : next,
                            );
                            resetPreview();
                            return;
                        }
                        setAttributes((previous) => ({
                            ...previous,
                            [field.name]: next,
                        }));
                        resetPreview();
                    };
                    if (field.type === "hidden") {
                        return null;
                    }
                    return (
                        <Field
                            key={field.name}
                            label={field.label || field.name}
                            description={field.description}
                        >
                            {(id) =>
                                field.type === "image" ||
                                field.type === "file" ? (
                                    <MediaPicker
                                        id={id}
                                        value={text}
                                        onChange={(path) => change(path)}
                                        listUrl={mediaUrl}
                                        uploadUrl={uploadUrl}
                                        imagesOnly={field.type === "image"}
                                    />
                                ) : field.type === "textarea" ? (
                                    <Textarea
                                        id={id}
                                        value={text}
                                        onChange={(event) =>
                                            change(event.target.value)
                                        }
                                    />
                                ) : field.type === "color" ? (
                                    <ColorPicker
                                        id={id}
                                        value={text}
                                        onChange={change}
                                    />
                                ) : field.type === "select" ? (
                                    <Select
                                        id={id}
                                        multiple={field.multiple}
                                        value={
                                            field.multiple
                                                ? Array.isArray(current)
                                                    ? current
                                                    : text
                                                          .split(",")
                                                          .filter(Boolean)
                                                : text
                                        }
                                        onValueChange={(value) =>
                                            change(
                                                field.multiple
                                                    ? [value]
                                                    : value,
                                            )
                                        }
                                        onValuesChange={(values) =>
                                            change(
                                                field.multiple
                                                    ? values
                                                    : (values[0] ?? ""),
                                            )
                                        }
                                    >
                                        {field.choices?.map((choice) => (
                                            <option
                                                key={choice.value}
                                                value={choice.value}
                                            >
                                                {choice.label}
                                            </option>
                                        ))}
                                    </Select>
                                ) : field.type === "checkbox" ? (
                                    <Checkbox
                                        id={id}
                                        checked={
                                            text === "1" || text === "true"
                                        }
                                        onCheckedChange={(checked) =>
                                            change(checked === true ? "1" : "0")
                                        }
                                    />
                                ) : (
                                    <Input
                                        id={id}
                                        type={
                                            ["number", "email"].includes(
                                                field.type,
                                            )
                                                ? field.type
                                                : "text"
                                        }
                                        value={text}
                                        onChange={(event) =>
                                            change(event.target.value)
                                        }
                                    />
                                )
                            }
                        </Field>
                    );
                })}
                {showFallbackContent && (
                    <Field label={t("Block content")}>
                        {(id) => (
                            <Textarea
                                id={id}
                                value={shortcodeContent}
                                onChange={(event) => {
                                    setShortcodeContent(event.target.value);
                                    resetPreview();
                                }}
                            />
                        )}
                    </Field>
                )}
                <details className="rounded-md border bg-muted/20">
                    <summary className="cursor-pointer px-3 py-2 text-sm">
                        {t("Shortcode")}
                    </summary>
                    <pre className="max-h-40 overflow-auto whitespace-pre-wrap break-words border-t p-3 text-xs [overflow-wrap:anywhere]">
                        {generatedCode()}
                    </pre>
                </details>
                {previewError && (
                    <p role="alert" className="text-sm text-destructive">
                        {previewError}
                    </p>
                )}
                {preview && (
                    <iframe
                        title={t("Shortcode preview")}
                        sandbox=""
                        srcDoc={preview}
                        className="h-64 w-full rounded border"
                    />
                )}
            </div>
        </Dialog>
    );
}
