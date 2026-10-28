import { lazy, Suspense, useState, type ComponentProps } from "react";
import { createRetryableLoader } from "../lib/retryable-loader";
import { useAdminTranslation } from "../lib/translate";
import { ClientErrorBoundary } from "./client-error-boundary";
import { useFieldControl } from "./field";
import { LoadingIndicator } from "./loading-indicator";
export type { Shortcode } from "./rich-editor-implementation";
type Props = ComponentProps<
    typeof import("./rich-editor-implementation").RichEditor
>;
const loader = createRetryableLoader(() =>
    import("./rich-editor-implementation").then((module) => ({
        default: module.RichEditor,
    })),
);

export function RichEditor(props: Props) {
    const t = useAdminTranslation();
    const control = useFieldControl(props.id);
    const [attempt, setAttempt] = useState(0);
    const [Editor, setEditor] = useState(() => lazy(loader.load));
    const retry = () => {
        loader.reset();
        setEditor(() => lazy(loader.load));
        setAttempt((current) => current + 1);
    };
    return (
        <ClientErrorBoundary
            key={attempt}
            source="editor"
            fallback={() => (
                <div className="space-y-3 rounded border p-3">
                    <p role="alert">
                        {t(
                            "The editor could not load. Your content is still available below.",
                        )}
                    </p>
                    <button
                        type="button"
                        className="rounded border p-2 focus-visible:outline-2 focus-visible:outline-ring"
                        onClick={retry}
                    >
                        {t("Retry editor")}
                    </button>
                    <textarea
                        {...control}
                        id={props.id}
                        aria-label={t("HTML source")}
                        className="min-h-80 w-full rounded border p-3 font-mono"
                        value={props.value}
                        onChange={(event) => props.onChange(event.target.value)}
                    />
                </div>
            )}
        >
            <Suspense
                fallback={
                    <LoadingIndicator
                        label={t("Loading editor…")}
                        className="flex min-h-80 rounded border p-4"
                    />
                }
            >
                <Editor {...props} />
            </Suspense>
        </ClientErrorBoundary>
    );
}
