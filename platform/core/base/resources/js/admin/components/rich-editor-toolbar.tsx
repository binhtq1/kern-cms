import { ColorPicker } from "./color-picker";
import { RichEditorAdvancedTools } from "./rich-editor-advanced-tools";
import { Checkbox } from "./ui/checkbox";
import type { Editor } from "@tiptap/react";
import {
    Bold,
    Italic,
    Underline,
    Strikethrough,
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    List,
    ListOrdered,
    Quote,
    Code,
    Eraser,
    Undo,
    Redo,
    Link,
    Table2,
    Video,
} from "lucide-react";
import { useState, type ComponentType } from "react";
import { Button } from "./ui/button";
import { Select } from "./ui/select";
import { Input } from "./ui/input";
import { Dialog } from "./ui/dialog";
import { DropdownMenu, DropdownMenuItem } from "./ui/dropdown-menu";
import { useAdminTranslation } from "@admin/lib/translate";
import { isValidYoutubeUrl } from "@tiptap/extension-youtube";

export function RichEditorToolbar({ editor }: { editor: Editor }) {
    const t = useAdminTranslation();
    const [embed, setEmbed] = useState<"link" | "video" | null>(null);
    const [moreTools, setMoreTools] = useState(false);
    const [url, setUrl] = useState("");
    const [error, setError] = useState("");
    const [newTab, setNewTab] = useState(true);
    function applyEmbed() {
        const next = url.trim();
        if (embed === "video") {
            if (!isValidYoutubeUrl(next)) {
                setError(t("Enter a valid YouTube URL."));
                return;
            }
            editor.chain().focus().setYoutubeVideo({ src: next }).run();
        } else if (!next) {
            editor.chain().focus().extendMarkRange("link").unsetLink().run();
        } else if (/^(https?:\/\/|mailto:|\/|#)/i.test(next)) {
            editor
                .chain()
                .focus()
                .extendMarkRange("link")
                .setLink({
                    href: next,
                    target: newTab ? "_blank" : "_self",
                    rel: newTab ? "noopener noreferrer" : null,
                })
                .run();
        } else {
            setError(t("Enter a valid link URL."));
            return;
        }
        setEmbed(null);
    }
    const tool = (
        label: string,
        Icon: ComponentType<{ size?: number }>,
        action: () => unknown,
        active = false,
        disabled = false,
    ) => (
        <Button
            type="button"
            variant={active ? "secondary" : "ghost"}
            size="icon"
            aria-label={t(label)}
            title={t(label)}
            aria-pressed={active}
            disabled={disabled}
            onClick={action}
            onMouseDown={(event) => event.preventDefault()}
        >
            <Icon size={16} />
        </Button>
    );
    const tableActions: [string, () => boolean, boolean][] = [
        [
            "Insert table",
            () =>
                editor
                    .chain()
                    .focus()
                    .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                    .run(),
            !editor.isActive("table"),
        ],
        [
            "Add row above",
            () => editor.chain().focus().addRowBefore().run(),
            editor.can().addRowBefore(),
        ],
        [
            "Add row below",
            () => editor.chain().focus().addRowAfter().run(),
            editor.can().addRowAfter(),
        ],
        [
            "Delete row",
            () => editor.chain().focus().deleteRow().run(),
            editor.can().deleteRow(),
        ],
        [
            "Add column before",
            () => editor.chain().focus().addColumnBefore().run(),
            editor.can().addColumnBefore(),
        ],
        [
            "Add column after",
            () => editor.chain().focus().addColumnAfter().run(),
            editor.can().addColumnAfter(),
        ],
        [
            "Delete column",
            () => editor.chain().focus().deleteColumn().run(),
            editor.can().deleteColumn(),
        ],
        [
            "Toggle header row",
            () => editor.chain().focus().toggleHeaderRow().run(),
            editor.can().toggleHeaderRow(),
        ],
        [
            "Merge cells",
            () => editor.chain().focus().mergeCells().run(),
            editor.can().mergeCells(),
        ],
        [
            "Split cell",
            () => editor.chain().focus().splitCell().run(),
            editor.can().splitCell(),
        ],
        [
            "Delete table",
            () => editor.chain().focus().deleteTable().run(),
            editor.can().deleteTable(),
        ],
    ];
    const style = editor.getAttributes("textStyle");
    return (
        <>
            <Select
                aria-label={t("Block format")}
                containerClassName="w-36"
                className="sm:h-9 sm:text-xs"
                value={
                    editor.isActive("heading")
                        ? `h${editor.getAttributes("heading").level}`
                        : "paragraph"
                }
                onValueChange={(value) => {
                    if (value === "paragraph") {
                        editor.chain().focus().setParagraph().run();
                    } else {
                        editor
                            .chain()
                            .focus()
                            .setHeading({
                                level: Number(value.slice(1)) as
                                    1 | 2 | 3 | 4 | 5 | 6,
                            })
                            .run();
                    }
                }}
            >
                <option value="paragraph">{t("Paragraph")}</option>
                {[1, 2, 3, 4, 5, 6].map((level) => (
                    <option key={level} value={`h${level}`}>
                        {t("Heading")} {level}
                    </option>
                ))}
            </Select>
            {tool(
                "Bold",
                Bold,
                () => editor.chain().focus().toggleBold().run(),
                editor.isActive("bold"),
            )}
            {tool(
                "Italic",
                Italic,
                () => editor.chain().focus().toggleItalic().run(),
                editor.isActive("italic"),
            )}
            {tool(
                "Underline",
                Underline,
                () => editor.chain().focus().toggleUnderline().run(),
                editor.isActive("underline"),
            )}
            {tool(
                "Strikethrough",
                Strikethrough,
                () => editor.chain().focus().toggleStrike().run(),
                editor.isActive("strike"),
            )}
            <div className="contents">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="sm:hidden"
                    aria-expanded={moreTools}
                    onClick={() => setMoreTools(!moreTools)}
                >
                    {t("More formatting")}
                </Button>
                <div
                    className={`${moreTools ? "flex" : "hidden"} w-full flex-wrap items-center gap-1 sm:contents`}
                >
                    <Select
                        aria-label={t("Font family")}
                        containerClassName="w-36"
                        className="sm:h-9 sm:text-xs"
                        value={style.fontFamily ?? "default"}
                        onValueChange={(value) =>
                            value === "default"
                                ? editor.chain().focus().unsetFontFamily().run()
                                : editor
                                      .chain()
                                      .focus()
                                      .setFontFamily(value)
                                      .run()
                        }
                    >
                        <option value="default">{t("Default font")}</option>
                        {style.fontFamily &&
                            ![
                                "Arial",
                                "Georgia",
                                "Times New Roman",
                                "Courier New",
                                "Verdana",
                            ].includes(style.fontFamily) && (
                                <option value={style.fontFamily}>
                                    {style.fontFamily}
                                </option>
                            )}
                        {[
                            "Arial",
                            "Georgia",
                            "Times New Roman",
                            "Courier New",
                            "Verdana",
                        ].map((font) => (
                            <option key={font} value={font}>
                                {font}
                            </option>
                        ))}
                    </Select>
                    <Select
                        aria-label={t("Font size")}
                        containerClassName="w-28"
                        className="sm:h-9 sm:text-xs"
                        value={style.fontSize ?? "default"}
                        onValueChange={(value) =>
                            value === "default"
                                ? editor.chain().focus().unsetFontSize().run()
                                : editor
                                      .chain()
                                      .focus()
                                      .setFontSize(value)
                                      .run()
                        }
                    >
                        <option value="default">{t("Font size")}</option>
                        {style.fontSize &&
                            ![10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48]
                                .map((size) => `${size}px`)
                                .includes(style.fontSize) && (
                                <option value={style.fontSize}>
                                    {style.fontSize}
                                </option>
                            )}
                        {[10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48].map(
                            (size) => (
                                <option key={size} value={`${size}px`}>
                                    {size}px
                                </option>
                            ),
                        )}
                    </Select>
                    <ColorPicker
                        compact
                        label={t("Text color")}
                        value={style.color ?? "#222222"}
                        onChange={(value) =>
                            value
                                ? editor.chain().focus().setColor(value).run()
                                : editor.chain().focus().unsetColor().run()
                        }
                    />
                    <ColorPicker
                        compact
                        label={t("Background color")}
                        value={style.backgroundColor ?? ""}
                        onChange={(value) =>
                            value
                                ? editor
                                      .chain()
                                      .focus()
                                      .setBackgroundColor(value)
                                      .run()
                                : editor
                                      .chain()
                                      .focus()
                                      .unsetBackgroundColor()
                                      .run()
                        }
                    />
                    {(
                        [
                            ["Align left", AlignLeft, "left"],
                            ["Align center", AlignCenter, "center"],
                            ["Align right", AlignRight, "right"],
                            ["Justify", AlignJustify, "justify"],
                        ] as const
                    ).map(([label, Icon, align]) => (
                        <span key={align}>
                            {tool(
                                label,
                                Icon,
                                () =>
                                    editor
                                        .chain()
                                        .focus()
                                        .setTextAlign(align)
                                        .run(),
                                editor.isActive({ textAlign: align }),
                            )}
                        </span>
                    ))}
                    {tool(
                        "Bullet list",
                        List,
                        () => editor.chain().focus().toggleBulletList().run(),
                        editor.isActive("bulletList"),
                    )}
                    {tool(
                        "Numbered list",
                        ListOrdered,
                        () => editor.chain().focus().toggleOrderedList().run(),
                        editor.isActive("orderedList"),
                    )}
                    {tool(
                        "Blockquote",
                        Quote,
                        () => editor.chain().focus().toggleBlockquote().run(),
                        editor.isActive("blockquote"),
                    )}
                    {tool(
                        "Code block",
                        Code,
                        () => editor.chain().focus().toggleCodeBlock().run(),
                        editor.isActive("codeBlock"),
                    )}
                    {tool("Clear formatting", Eraser, () =>
                        editor
                            .chain()
                            .focus()
                            .unsetAllMarks()
                            .clearNodes()
                            .unsetTextAlign()
                            .run(),
                    )}
                    {tool(
                        "Insert link",
                        Link,
                        () => {
                            setEmbed("link");
                            setUrl(editor.getAttributes("link").href ?? "");
                            setNewTab(
                                editor.getAttributes("link").target !== "_self",
                            );
                            setError("");
                        },
                        editor.isActive("link"),
                    )}
                    <DropdownMenu
                        label={t("Table actions")}
                        trigger={
                            <>
                                <Table2 size={16} />
                                {t("Table")}
                            </>
                        }
                        align="start"
                    >
                        {tableActions.map(([label, action, enabled]) => (
                            <DropdownMenuItem
                                key={label}
                                disabled={!enabled}
                                onSelect={() => {
                                    action();
                                }}
                            >
                                {t(label)}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenu>
                    {tool("Insert YouTube video", Video, () => {
                        setEmbed("video");
                        setUrl("");
                        setError("");
                    })}
                    <RichEditorAdvancedTools editor={editor} />
                </div>
            </div>
            {tool(
                "Undo",
                Undo,
                () => editor.chain().focus().undo().run(),
                false,
                !editor.can().undo(),
            )}
            {tool(
                "Redo",
                Redo,
                () => editor.chain().focus().redo().run(),
                false,
                !editor.can().redo(),
            )}
            <Dialog
                open={embed !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEmbed(null);
                    }
                }}
                title={t(
                    embed === "video" ? "Insert YouTube video" : "Insert link",
                )}
                description={t(
                    embed === "video"
                        ? "Paste a YouTube video URL."
                        : "Enter a URL, or leave it blank to remove the link.",
                )}
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setEmbed(null)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button onClick={applyEmbed}>{t("Apply")}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Input
                        aria-label={t("URL")}
                        value={url}
                        onChange={(event) => {
                            setUrl(event.target.value);
                            setError("");
                        }}
                        placeholder="https://"
                    />
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    {embed === "link" && (
                        <label className="flex items-center gap-2">
                            <Checkbox
                                checked={newTab}
                                onCheckedChange={(checked) =>
                                    setNewTab(checked === true)
                                }
                            />
                            {t("Open in a new tab")}
                        </label>
                    )}
                </div>
            </Dialog>
        </>
    );
}
