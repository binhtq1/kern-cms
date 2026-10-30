import {
    checkAdminFetchResponse,
    currentCsrfHeaders,
} from "@admin/lib/admin-session";
import { parseColor } from "@admin/lib/color";
import { createLatestRequest } from "@admin/lib/latest-request";
import { useAdminTranslation } from "@admin/lib/translate";
import { useEffect, useState } from "react";

import {
    escapeAttribute,
    readAttributes,
    type Shortcode,
} from "../lib/rich-editor-shortcodes";
import {
    findShortcodes,
    patchShortcode,
    type ShortcodeAttributes,
} from "../lib/shortcode-parser";
export function useEditorShortcode(value: string) {
    const t = useAdminTranslation();
    const [shortcode, setShortcode] = useState<Shortcode | null>(null);
    const [attributes, setAttributes] = useState<
        Record<string, string | string[]>
    >({});
    const [shortcodeContent, setShortcodeContent] = useState("");
    const [editingCode, setEditingCode] = useState("");
    const [initialAttributes, setInitialAttributes] =
        useState<ShortcodeAttributes>({});
    const [initialContent, setInitialContent] = useState("");
    const [saveError, setSaveError] = useState("");
    const [preview, setPreview] = useState("");
    const [previewError, setPreviewError] = useState("");
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewRequest] = useState(createLatestRequest);
    useEffect(() => () => previewRequest.cancel(), [previewRequest]);
    function resetPreview() {
        previewRequest.cancel();
        setPreview("");
        setPreviewError("");
        setPreviewLoading(false);
    }
    function closeShortcode() {
        resetPreview();
        setShortcode(null);
    }
    const hasInvalidColor = (shortcode?.fields ?? []).some((field) => {
        if (field.type !== "color") return false;
        const color = String(attributes[field.name] ?? "");
        return color !== "" && !parseColor(color);
    });
    const hasContentField = shortcode?.fields?.some(
        (field) => field.name === "content",
    );
    const showFallbackContent =
        !hasContentField &&
        (!shortcode?.fields?.length ||
            !!findShortcodes(editingCode)[0]?.content);
    const existing = findShortcodes(value);
    function configure(item: Shortcode, code = "") {
        const parsed = readAttributes(code);
        setShortcode(item);
        setEditingCode(code);
        setSaveError("");
        resetPreview();
        const nextAttributes = {
            ...parsed,
            ...Object.fromEntries(
                (item.fields ?? [])
                    .map((field) => [
                        field.name,
                        parsed[field.name] ?? field.value ?? "",
                    ])
                    .map(([key, value]) => [
                        key,
                        typeof value === "boolean"
                            ? value
                                ? "1"
                                : "0"
                            : Array.isArray(value)
                              ? value
                              : String(value),
                    ]),
            ),
        };
        setAttributes(nextAttributes);
        setInitialAttributes(nextAttributes);
        const inner = code
            ? (findShortcodes(code)[0]?.content ?? "")
            : String(
                  item.fields?.find((field) => field.name === "content")
                      ?.value ?? "",
              );
        setShortcodeContent(inner);
        setInitialContent(inner);
    }
    function generatedCode() {
        if (!shortcode) {
            return "";
        }
        if (editingCode)
            return patchShortcode(
                editingCode,
                initialAttributes,
                attributes,
                initialContent,
                shortcodeContent,
                escapeAttribute,
            );
        const props = Object.entries(attributes)
            .filter(([key]) => key !== "content")
            .map(
                ([key, raw]) =>
                    ` ${key}="${escapeAttribute(Array.isArray(raw) ? raw.join(",") : raw)}"`,
            )
            .join("");
        return `[${shortcode.key}${props}]${shortcodeContent}[/${shortcode.key}]`;
    }
    async function previewShortcode() {
        if (!shortcode?.previewUrl) {
            return;
        }
        setPreviewLoading(true);
        setPreview("");
        setPreviewError("");
        await previewRequest.run(
            async (signal) => {
                const response = await fetch(shortcode.previewUrl!, {
                    method: "POST",
                    signal,
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        ...currentCsrfHeaders(
                            undefined,
                            undefined,
                            shortcode.previewUrl!,
                        ),
                    },
                    body: JSON.stringify({
                        name: shortcode.key,
                        content: shortcodeContent,
                        attributes: Object.fromEntries(
                            Object.entries(attributes)
                                .filter(([key]) => key !== "content")
                                .map(([key, value]) => [
                                    key,
                                    Array.isArray(value)
                                        ? value.join(",")
                                        : value,
                                ]),
                        ),
                    }),
                });
                await checkAdminFetchResponse(response);
                const result = await response.json();
                if (!response.ok || result.error) {
                    throw new Error(
                        result.message ||
                            t("Unable to preview this shortcode."),
                    );
                }
                return result.data ?? "";
            },
            {
                onSuccess: setPreview,
                onError: (reason) =>
                    setPreviewError(
                        reason instanceof Error
                            ? reason.message
                            : t("Unable to preview this shortcode."),
                    ),
                onSettled: () => setPreviewLoading(false),
            },
        );
    }

    return {
        shortcode,
        attributes,
        setAttributes,
        shortcodeContent,
        setShortcodeContent,
        editingCode,
        saveError,
        setSaveError,
        preview,
        previewError,
        previewLoading,
        resetPreview,
        closeShortcode,
        hasInvalidColor,
        showFallbackContent,
        existing,
        configure,
        generatedCode,
        previewShortcode,
    };
}
