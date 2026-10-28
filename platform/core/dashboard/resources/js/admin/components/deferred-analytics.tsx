import { ClientErrorBoundary } from "@admin/components/client-error-boundary";
import { createRetryableLoader } from "@admin/lib/retryable-loader";
import { useAdminTranslation } from "@admin/lib/translate";
import { lazy, Suspense, useState, type ComponentProps } from "react";
import { WidgetLoading } from "./widget-loading";
type Props = ComponentProps<
    typeof import("../Dashboard/analytics-visuals").AnalyticsVisuals
>;
const loader = createRetryableLoader(() =>
    import("../Dashboard/analytics-visuals").then((module) => ({
        default: module.AnalyticsVisuals,
    })),
);
export function DeferredAnalytics(props: Props) {
    const t = useAdminTranslation();
    const [attempt, setAttempt] = useState(0);
    const [Visuals, setVisuals] = useState(() => lazy(loader.load));
    const retry = () => {
        loader.reset();
        setVisuals(() => lazy(loader.load));
        setAttempt((value) => value + 1);
    };
    return (
        <ClientErrorBoundary
            key={attempt}
            source="analytics"
            fallback={() => (
                <div className="min-h-64 rounded border p-4">
                    <p role="alert">
                        {t("Charts could not load. Retry when you are ready.")}
                    </p>
                    <button
                        type="button"
                        className="mt-3 rounded border p-2 focus-visible:outline-2 focus-visible:outline-ring"
                        onClick={retry}
                    >
                        {t("Retry charts")}
                    </button>
                </div>
            )}
        >
            <Suspense
                fallback={
                    <WidgetLoading
                        label={t("Loading charts…")}
                        className="min-h-64 rounded border p-4"
                    />
                }
            >
                <Visuals {...props} />
            </Suspense>
        </ClientErrorBoundary>
    );
}
