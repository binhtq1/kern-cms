import axios from "axios";
import { useState } from "react";
import { ExternalLink, WandSparkles } from "lucide-react";
import { useAdminTranslation } from "@admin/lib/translate";
import { Field } from "./field";
import { Button } from "./ui/button";
import { Input } from "./ui/input";

export type PermalinkData = {
    pattern: string;
    ending: string;
    preview: boolean;
    homepage: string | null;
    generateUrl: string;
    slugId: string;
    model: string;
};

export function PermalinkField({
    value,
    onChange,
    name,
    permalink,
    error,
    disabled = false,
}: {
    value: string;
    onChange: (value: string) => void;
    name: string;
    permalink?: PermalinkData | null;
    error?: string;
    disabled?: boolean;
}) {
    const t = useAdminTranslation();
    const [busy, setBusy] = useState(false);
    const [generationError, setGenerationError] = useState("");
    const previewUrl =
        permalink?.homepage ||
        (value && permalink
            ? permalink.pattern.replace("--slug--", encodeURIComponent(value)) +
              permalink.ending +
              (permalink.preview ? "?preview=true" : "")
            : "");
    async function generate() {
        if (!permalink || disabled || busy || !name.trim()) return;
        setBusy(true);
        setGenerationError("");
        try {
            const { data } = await axios.post(permalink.generateUrl, {
                value: name,
                slug_id: permalink.slugId,
                model: permalink.model,
            });
            if (typeof data !== "string" && typeof data !== "number")
                throw new Error();
            onChange(String(data));
        } catch {
            setGenerationError(t("Unable to generate URL. Please try again."));
        } finally {
            setBusy(false);
        }
    }
    return (
        <Field
            label={t("Permalink")}
            error={error || generationError}
            description={
                previewUrl ? (
                    <a
                        href={previewUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex max-w-full items-start gap-1 text-primary underline-offset-4 hover:underline"
                    >
                        <span className="break-all">{previewUrl}</span>
                        <ExternalLink className="mt-0.5 size-3 shrink-0" />
                    </a>
                ) : (
                    t("Leave empty to generate a URL from the name.")
                )
            }
        >
            {(id) => (
                <div className="flex gap-2">
                    <Input
                        id={id}
                        className="min-w-0"
                        value={permalink?.homepage || value}
                        readOnly={!!permalink?.homepage}
                        disabled={disabled || busy}
                        onChange={(event) => onChange(event.target.value)}
                    />
                    {permalink && !permalink.homepage && (
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={t("Generate URL")}
                            title={t("Generate URL")}
                            disabled={disabled || !name.trim()}
                            loading={busy}
                            onClick={generate}
                        >
                            <WandSparkles />
                        </Button>
                    )}
                </div>
            )}
        </Field>
    );
}
