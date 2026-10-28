import { useEffect, useId, useState } from "react";
import type { Editor } from "@tiptap/react";
import {
    Search,
    CodeXml,
    Video,
    Image,
    Settings2,
    IndentIncrease,
    IndentDecrease,
    PilcrowLeft,
    PilcrowRight,
} from "lucide-react";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";
import { Input, Textarea } from "./ui/input";
import { Checkbox } from "./ui/checkbox";
import { Select } from "./ui/select";
import { ColorPicker } from "./color-picker";
import { useAdminTranslation } from "@admin/lib/translate";
import { parseColor } from "@admin/lib/color";
import {
    textMatches,
    validMediaUrl,
    validDimension,
    clampIndent,
} from "./rich-editor-tools";

type Mode = "find" | "html" | "media" | "image" | "table" | "cell";
export function RichEditorAdvancedTools({ editor }: { editor: Editor }) {
    const t = useAdminTranslation();
    const prefix = useId();
    const [mode, setMode] = useState<Mode | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const [find, setFind] = useState("");
    const [replacement, setReplacement] = useState("");
    const [caseSensitive, setCaseSensitive] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        editor.view.dispatch(
            editor.state.tr.setMeta("adminFind", {
                find: mode === "find" ? find : "",
                caseSensitive,
            }),
        );
    }, [editor, mode, find, caseSensitive]);
    useEffect(
        () => () => {
            if (!editor.isDestroyed)
                editor.view.dispatch(
                    editor.state.tr.setMeta("adminFind", {
                        find: "",
                        caseSensitive: false,
                    }),
                );
        },
        [editor],
    );
    const change = (name: string, value: string) => {
        setValues((previous) => ({ ...previous, [name]: value }));
        setError("");
    };
    const title = {
        find: "Find and replace",
        html: "HTML embed",
        media: "Insert media",
        image: "Image properties",
        table: "Table properties",
        cell: "Cell properties",
    };
    const [imageType, setImageType] = useState<"image" | "inlineImage">(
        "image",
    );
    function open(next: Mode) {
        setError("");
        let attrs = {} as Record<string, string>;
        if (["image", "table", "cell"].includes(next)) {
            const type =
                next === "cell"
                    ? editor.isActive("tableHeader")
                        ? "tableHeader"
                        : "tableCell"
                    : next === "image" && editor.isActive("inlineImage")
                      ? "inlineImage"
                      : next;
            if (next === "image") setImageType(type as "image" | "inlineImage");
            const current = editor.getAttributes(type);
            const style = document.createElement("div").style;
            style.cssText = current.legacy_style ?? "";
            attrs =
                next === "image"
                    ? {
                          src: current.src ?? "",
                          alt: current.alt ?? "",
                          title: current.title ?? "",
                          caption: current.caption ?? "",
                          width: String(current.width ?? ""),
                          height: String(current.height ?? ""),
                          layout: current.layout ?? "",
                      }
                    : {
                          width: style.width || current.legacy_width || "",
                          height: style.height || current.legacy_height || "",
                          color:
                              style.backgroundColor ||
                              current.legacy_bgcolor ||
                              "",
                          border: current.legacy_border || "",
                          padding: current.legacy_cellpadding || "",
                          spacing: current.legacy_cellspacing || "",
                          align: current.legacy_align || "",
                          valign: current.legacy_valign || "",
                      };
        } else if (next === "html")
            attrs.html = editor.getAttributes("htmlEmbed").html ?? "";
        else if (next === "media") attrs.type = "video";
        setValues(attrs);
        setMode(next);
    }
    function matches() {
        const ranges: { from: number; to: number }[] = [];
        editor.state.doc.descendants((node, position) => {
            if (!node.isTextblock) return;
            for (const index of textMatches(
                node.textBetween(0, node.content.size, "\n", "\uFFFC"),
                find,
                caseSensitive,
            ))
                ranges.push({
                    from: position + 1 + index,
                    to: position + 1 + index + find.length,
                });
            return false;
        });
        return ranges;
    }
    function findNext() {
        const ranges = matches();
        const range =
            ranges.find((item) => item.from >= editor.state.selection.to) ??
            ranges[0];
        if (range)
            editor.chain().setTextSelection(range).scrollIntoView().run();
    }
    function replace(all: boolean) {
        let ranges = matches();
        if (!all) {
            const current = editor.state.selection;
            ranges = [
                ranges.find(
                    (item) =>
                        item.from === current.from && item.to === current.to,
                ) ??
                    ranges.find((item) => item.from >= current.to) ??
                    ranges[0],
            ].filter(Boolean);
        }
        if (!ranges.length) return;
        const transaction = editor.state.tr;
        for (const range of ranges.reverse())
            transaction.insertText(replacement, range.from, range.to);
        editor.view.dispatch(transaction);
        if (!all) findNext();
    }
    function apply() {
        if (!mode) return;
        if (mode === "html") {
            if (!values.html?.trim()) {
                setError(t("Enter HTML content."));
                return;
            }
            if (editor.isActive("htmlEmbed"))
                editor
                    .chain()
                    .focus()
                    .updateAttributes("htmlEmbed", { html: values.html })
                    .run();
            else
                editor
                    .chain()
                    .focus()
                    .insertContent({
                        type: "htmlEmbed",
                        attrs: { html: values.html },
                    })
                    .run();
        } else if (mode === "media") {
            const url = values.url?.trim() ?? "";
            if (!validMediaUrl(url)) {
                setError(t("Enter a valid media URL."));
                return;
            }
            const tag =
                values.type === "iframe"
                    ? "iframe"
                    : values.type === "audio"
                      ? "audio"
                      : "video";
            const element = document.createElement(tag);
            element.setAttribute("src", url);
            if (tag === "iframe") {
                element.setAttribute("title", values.title || t("Media"));
                element.setAttribute("allowfullscreen", "");
            } else element.setAttribute("controls", "");
            editor
                .chain()
                .focus()
                .insertContent({
                    type: "htmlEmbed",
                    attrs: { html: element.outerHTML },
                })
                .run();
        } else {
            if (
                ![values.width ?? "", values.height ?? ""].every(
                    validDimension,
                ) ||
                (values.color && !parseColor(values.color)) ||
                [values.border, values.padding, values.spacing].some(
                    (value) => value && !/^\d{1,4}$/.test(value),
                )
            ) {
                setError(t("Enter valid dimensions and colors."));
                return;
            }
            if (mode === "image") {
                if (!validMediaUrl(values.src ?? "")) {
                    setError(t("Enter a valid media URL."));
                    return;
                }
                const imageStyle = document.createElement("div").style;
                imageStyle.cssText =
                    editor.getAttributes(imageType).imageStyle ?? "";
                imageStyle.width =
                    values.width && /^\d+$/.test(values.width)
                        ? `${values.width}px`
                        : values.width || "";
                imageStyle.height =
                    values.height && /^\d+$/.test(values.height)
                        ? `${values.height}px`
                        : values.height || "";
                imageStyle.cssFloat = values.layout || "";
                editor
                    .chain()
                    .focus()
                    .updateAttributes(imageType, {
                        src: values.src,
                        alt: values.alt,
                        title: values.title,
                        caption: values.caption,
                        width: values.width || null,
                        height: values.height || null,
                        layout: values.layout,
                        imageStyle: imageStyle.cssText || null,
                    })
                    .run();
            } else {
                const type =
                    mode === "cell"
                        ? editor.isActive("tableHeader")
                            ? "tableHeader"
                            : "tableCell"
                        : "table";
                const style = document.createElement("div").style;
                style.cssText = editor.getAttributes(type).legacy_style ?? "";
                style.width =
                    values.width && /^\d+$/.test(values.width)
                        ? `${values.width}px`
                        : values.width || "";
                style.height =
                    values.height && /^\d+$/.test(values.height)
                        ? `${values.height}px`
                        : values.height || "";
                style.backgroundColor = values.color || "";
                if (mode === "cell") {
                    style.textAlign = values.align || "";
                    style.verticalAlign = values.valign || "";
                }
                if (mode === "table") {
                    style.borderWidth = values.border
                        ? `${values.border}px`
                        : "";
                    style.borderStyle = values.border ? "solid" : "";
                    style.borderSpacing = values.spacing
                        ? `${values.spacing}px`
                        : "";
                    if (values.spacing) style.borderCollapse = "separate";
                    if (values.align) {
                        style.marginLeft =
                            values.align === "left" ? "0" : "auto";
                        style.marginRight =
                            values.align === "right" ? "0" : "auto";
                    }
                }
                const attrs = {
                    legacy_style: style.cssText || null,
                    legacy_width: values.width || null,
                    legacy_height: values.height || null,
                    legacy_bgcolor: values.color || null,
                    legacy_align: values.align || null,
                    legacy_valign: values.valign || null,
                };
                if (mode === "table")
                    editor
                        .chain()
                        .focus()
                        .updateAttributes(type, {
                            ...attrs,
                            legacy_border: values.border || null,
                            legacy_cellpadding: values.padding || null,
                            legacy_cellspacing: values.spacing || null,
                        })
                        .command(({ tr }) => {
                            const selection = tr.selection.$from;
                            for (
                                let depth = selection.depth;
                                depth > 0;
                                depth--
                            ) {
                                if (selection.node(depth).type.name !== "table")
                                    continue;
                                const position = selection.before(depth);
                                selection
                                    .node(depth)
                                    .descendants((node, offset) => {
                                        if (
                                            ![
                                                "tableCell",
                                                "tableHeader",
                                            ].includes(node.type.name)
                                        )
                                            return;
                                        const cellStyle =
                                            document.createElement("div").style;
                                        cellStyle.cssText =
                                            node.attrs.legacy_style ?? "";
                                        cellStyle.padding = values.padding
                                            ? `${values.padding}px`
                                            : "";
                                        cellStyle.borderWidth = values.border
                                            ? `${values.border}px`
                                            : "";
                                        cellStyle.borderStyle = values.border
                                            ? "solid"
                                            : "";
                                        tr.setNodeMarkup(
                                            position + 1 + offset,
                                            undefined,
                                            {
                                                ...node.attrs,
                                                legacy_style:
                                                    cellStyle.cssText || null,
                                            },
                                        );
                                    });
                                break;
                            }
                            return true;
                        })
                        .run();
                else {
                    const chain = editor.chain().focus();
                    for (const [name, value] of Object.entries(attrs))
                        chain.setCellAttribute(name, value);
                    chain.run();
                }
            }
        }
        setMode(null);
    }
    function indent(delta: number) {
        if (editor.isActive("listItem")) {
            const chain = editor.chain().focus();
            if (delta > 0) chain.sinkListItem("listItem").run();
            else chain.liftListItem("listItem").run();
            return;
        }
        const type = editor.isActive("heading") ? "heading" : "paragraph";
        editor
            .chain()
            .focus()
            .updateAttributes(type, {
                indent: clampIndent(
                    (editor.getAttributes(type).indent ?? 0) + delta,
                ),
            })
            .run();
    }
    const field = (name: string, label: string) => (
        <label className="block space-y-2" htmlFor={`${prefix}-${name}`}>
            <span className="text-sm font-medium">{t(label)}</span>
            <Input
                id={`${prefix}-${name}`}
                value={values[name] ?? ""}
                onChange={(event) => change(name, event.target.value)}
            />
        </label>
    );
    const tool = (
        label: string,
        Icon: typeof Search,
        action: () => void,
        disabled = false,
    ) => (
        <Button
            variant="ghost"
            size="icon"
            aria-label={t(label)}
            title={t(label)}
            disabled={disabled}
            onMouseDown={(event) => event.preventDefault()}
            onClick={action}
        >
            <Icon size={16} />
        </Button>
    );
    return (
        <>
            {tool("Find and replace", Search, () => open("find"))}
            {tool("Left to right", PilcrowLeft, () =>
                editor
                    .chain()
                    .focus()
                    .updateAttributes(
                        editor.isActive("heading") ? "heading" : "paragraph",
                        { direction: "ltr" },
                    )
                    .run(),
            )}
            {tool("Right to left", PilcrowRight, () =>
                editor
                    .chain()
                    .focus()
                    .updateAttributes(
                        editor.isActive("heading") ? "heading" : "paragraph",
                        { direction: "rtl" },
                    )
                    .run(),
            )}
            {tool("Increase indent", IndentIncrease, () => indent(1))}
            {tool("Decrease indent", IndentDecrease, () => indent(-1))}
            {tool("HTML embed", CodeXml, () => open("html"))}
            {tool("Insert media", Video, () => open("media"))}
            {tool(
                "Image properties",
                Image,
                () => open("image"),
                !editor.isActive("image") && !editor.isActive("inlineImage"),
            )}
            {tool(
                "Table properties",
                Settings2,
                () => open("table"),
                !editor.isActive("table"),
            )}
            {tool(
                "Cell properties",
                Settings2,
                () => open("cell"),
                !editor.isActive("tableCell") &&
                    !editor.isActive("tableHeader"),
            )}
            <Dialog
                open={mode !== null}
                onOpenChange={(open) => {
                    if (!open) setMode(null);
                }}
                title={t(mode ? title[mode] : "Editor tools")}
                footer={
                    mode === "find" ? (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setMode(null)}
                            >
                                {t("Close")}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={!matches().length}
                                onClick={findNext}
                            >
                                {t("Find next")}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={!matches().length}
                                onClick={() => replace(false)}
                            >
                                {t("Replace")}
                            </Button>
                            <Button
                                disabled={!matches().length}
                                onClick={() => replace(true)}
                            >
                                {t("Replace all")}
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setMode(null)}
                            >
                                {t("Cancel")}
                            </Button>
                            <Button onClick={apply}>{t("Apply")}</Button>
                        </>
                    )
                }
            >
                <div className="space-y-4">
                    {mode === "find" ? (
                        <>
                            <label className="block space-y-2">
                                <span>{t("Find")}</span>
                                <Input
                                    value={find}
                                    onChange={(event) =>
                                        setFind(event.target.value)
                                    }
                                />
                            </label>
                            <label className="block space-y-2">
                                <span>{t("Replace with")}</span>
                                <Input
                                    value={replacement}
                                    onChange={(event) =>
                                        setReplacement(event.target.value)
                                    }
                                />
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={caseSensitive}
                                    onCheckedChange={(checked) =>
                                        setCaseSensitive(checked === true)
                                    }
                                />
                                {t("Match case")}
                            </label>
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {t(":count matches", {
                                    count: matches().length,
                                })}
                            </p>
                        </>
                    ) : mode === "html" ? (
                        <label className="block space-y-2">
                            <span>{t("HTML content")}</span>
                            <Textarea
                                className="min-h-60 font-mono"
                                value={values.html ?? ""}
                                onChange={(event) =>
                                    change("html", event.target.value)
                                }
                            />
                        </label>
                    ) : mode === "media" ? (
                        <>
                            {field("url", "URL")}
                            {field("title", "Title")}
                            <label className="block space-y-2">
                                <span>{t("Media type")}</span>
                                <Select
                                    aria-label={t("Media type")}
                                    value={values.type}
                                    onValueChange={(value) =>
                                        change("type", value)
                                    }
                                >
                                    <option value="video">{t("Video")}</option>
                                    <option value="audio">{t("Audio")}</option>
                                    <option value="iframe">
                                        {t("Embedded player")}
                                    </option>
                                </Select>
                            </label>
                        </>
                    ) : (
                        <>
                            {mode === "image" && (
                                <>
                                    {field("src", "Image URL")}
                                    {field("alt", "Alternative text")}
                                    {field("title", "Title")}
                                    {imageType === "image" ? (
                                        field("caption", "Caption")
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                "Captions are available for block images.",
                                            )}
                                        </p>
                                    )}
                                </>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                {field("width", "Width")}
                                {field("height", "Height")}
                            </div>
                            {mode === "image" ? (
                                <label className="block space-y-2">
                                    <span>{t("Image layout")}</span>
                                    <Select
                                        aria-label={t("Image layout")}
                                        value={values.layout}
                                        onValueChange={(value) =>
                                            change("layout", value)
                                        }
                                    >
                                        <option value="">{t("In flow")}</option>
                                        <option value="left">
                                            {t("Float left")}
                                        </option>
                                        <option value="right">
                                            {t("Float right")}
                                        </option>
                                    </Select>
                                </label>
                            ) : (
                                <>
                                    <ColorPicker
                                        label={t("Background color")}
                                        value={values.color ?? ""}
                                        onChange={(value) =>
                                            change("color", value)
                                        }
                                    />
                                    {mode === "table" && (
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            {field("border", "Border width")}
                                            {field("padding", "Cell padding")}
                                            {field("spacing", "Cell spacing")}
                                        </div>
                                    )}
                                    <label className="block space-y-2">
                                        <span>{t("Alignment")}</span>
                                        <Select
                                            aria-label={t("Alignment")}
                                            value={values.align}
                                            onValueChange={(value) =>
                                                change("align", value)
                                            }
                                        >
                                            <option value="">
                                                {t("Default")}
                                            </option>
                                            <option value="left">
                                                {t("Left")}
                                            </option>
                                            <option value="center">
                                                {t("Center")}
                                            </option>
                                            <option value="right">
                                                {t("Right")}
                                            </option>
                                        </Select>
                                    </label>
                                    {mode === "cell" && (
                                        <label className="block space-y-2">
                                            <span>
                                                {t("Vertical alignment")}
                                            </span>
                                            <Select
                                                aria-label={t(
                                                    "Vertical alignment",
                                                )}
                                                value={values.valign}
                                                onValueChange={(value) =>
                                                    change("valign", value)
                                                }
                                            >
                                                <option value="">
                                                    {t("Default")}
                                                </option>
                                                <option value="top">
                                                    {t("Top")}
                                                </option>
                                                <option value="middle">
                                                    {t("Middle")}
                                                </option>
                                                <option value="bottom">
                                                    {t("Bottom")}
                                                </option>
                                            </Select>
                                        </label>
                                    )}
                                </>
                            )}
                        </>
                    )}
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                </div>
            </Dialog>
        </>
    );
}
