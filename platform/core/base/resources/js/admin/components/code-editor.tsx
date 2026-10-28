import { useFieldControl } from "./field";
import { useId, useRef, useState } from "react";
import { useAdminTranslation } from "@admin/lib/translate";
import { Button } from "./ui/button";

export type CodeEditorProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    mode?: string;
    disabled?: boolean;
    readOnly?: boolean;
    placeholder?: string;
};
export function CodeEditor({
    id,
    value,
    onChange,
    mode = "htmlmixed",
    disabled,
    readOnly,
    placeholder,
}: CodeEditorProps) {
    const t = useAdminTranslation();
    const generatedId = useId();
    const field = useFieldControl(id);
    const [preview, setPreview] = useState(false);
    const gutter = useRef<HTMLDivElement>(null);
    const tokens = value.split(
        /(<!--[\s\S]*?-->|\/\*[\s\S]*?\*\/|"[^"\n]*"|'[^'\n]*'|<\/?[\w-]+|\b(?:const|let|var|function|return|if|else|true|false|null|import|export|from|class|new)\b|#[\da-fA-F]{3,8}\b|\b\d+(?:\.\d+)?\b)/g,
    );
    return (
        <div className="min-w-0 overflow-hidden rounded-md border bg-background focus-within:ring-2 focus-within:ring-ring">
            <div className="flex items-center justify-between border-b bg-muted/30 px-3 py-1.5">
                <span className="font-mono text-xs text-muted-foreground">
                    {mode}
                </span>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setPreview(!preview)}
                    aria-pressed={preview}
                >
                    {t(preview ? "Edit code" : "Preview code")}
                </Button>
            </div>
            {preview ? (
                <pre
                    className="max-h-[32rem] min-h-64 overflow-auto p-3 font-mono text-xs leading-6"
                    tabIndex={0}
                    aria-label={t("Code preview")}
                >
                    {tokens.map((token, index) => (
                        <span
                            key={index}
                            className={
                                /^<!--|^\/\*/.test(token)
                                    ? "text-muted-foreground"
                                    : /^["']/.test(token)
                                      ? "text-emerald-800 dark:text-emerald-300"
                                      : /^<|^(const|let|var|function|return|if|else|true|false|null|import|export|from|class|new)$/.test(
                                              token,
                                          )
                                        ? "text-violet-800 dark:text-violet-300"
                                        : /^#|^\d/.test(token)
                                          ? "text-amber-800 dark:text-amber-300"
                                          : undefined
                            }
                        >
                            {token}
                        </span>
                    ))}
                </pre>
            ) : (
                <div className="flex min-w-0">
                    <div
                        ref={gutter}
                        aria-hidden="true"
                        className="max-h-[32rem] w-12 shrink-0 overflow-hidden border-r bg-muted/20 py-3 pr-2 text-right font-mono text-xs leading-6 text-muted-foreground"
                    >
                        {value.split("\n").map((_, index) => (
                            <div key={index}>{index + 1}</div>
                        ))}
                    </div>
                    <textarea
                        {...field}
                        id={id ?? generatedId}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        disabled={disabled}
                        readOnly={readOnly}
                        placeholder={placeholder}
                        spellCheck={false}
                        wrap="off"
                        className="min-h-64 w-full min-w-0 resize-y overflow-auto bg-transparent p-3 font-mono text-xs leading-6 outline-none disabled:opacity-50"
                        onScroll={(event) => {
                            if (gutter.current)
                                gutter.current.scrollTop =
                                    event.currentTarget.scrollTop;
                        }}
                    />
                </div>
            )}
        </div>
    );
}
