import {
    ContentResourceIndex,
    type ContentIndexProps,
} from "@admin/components/content-resource-index";
import { Button } from "@admin/components/ui/button";
import { Dialog } from "@admin/components/ui/dialog";
import { parseJsonImport } from "@admin/lib/json-import";
import { Textarea } from "@admin/components/ui/input";
import { Field } from "@admin/components/field";
import { FileUploadField } from "@admin/components/file-upload-field";
import { useState } from "react";
import { router } from "@inertiajs/react";
import { useAdminTranslation } from "@admin/lib/translate";

const maxJsonBytes = 2 * 1024 * 1024;

export default function FieldGroupsIndex(
    props: ContentIndexProps & {
        urls: ContentIndexProps["urls"] & { import: string; export: string };
    },
) {
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const [json, setJson] = useState("");
    const [error, setError] = useState("");
    const [file, setFile] = useState<File | null>(null);
    const [fileError, setFileError] = useState("");
    const [reading, setReading] = useState(false);
    const [busy, setBusy] = useState(false);
    async function readFile(next: File) {
        setReading(true);
        setFileError("");
        try {
            const text = await next.text();
            parseJsonImport(text, maxJsonBytes, 100);
            setJson(text);
            setFile(next);
            setError("");
        } catch (error) {
            setFileError(
                t(
                    error instanceof SyntaxError
                        ? "Invalid JSON"
                        : error instanceof Error &&
                            error.message === "Invalid JSON data"
                          ? "Invalid JSON data"
                          : "Could not read the JSON file.",
                ),
            );
        } finally {
            setReading(false);
        }
    }
    return (
        <>
            <ContentResourceIndex
                {...props}
                extraActions={
                    <>
                        <Button variant="outline" onClick={() => setOpen(true)}>
                            {t("Import")}
                        </Button>
                    </>
                }
            />
            <Dialog
                open={open}
                onOpenChange={(value) => !busy && !reading && setOpen(value)}
                title={t("Import")}
            >
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (busy || reading) return;
                        try {
                            const data = parseJsonImport(
                                json,
                                maxJsonBytes,
                                100,
                            );
                            setError("");
                            setBusy(true);
                            router.post(
                                props.urls.import,
                                { json_data: data },
                                {
                                    onSuccess: () => {
                                        setOpen(false);
                                        router.reload();
                                    },
                                    onError: (errors) =>
                                        setError(Object.values(errors)[0]),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        } catch {
                            setError(t("Invalid JSON"));
                        }
                    }}
                >
                    <Field label={t("JSON file")} error={fileError}>
                        {(id) => (
                            <FileUploadField
                                id={id}
                                accept=".json,application/json"
                                formats=".json"
                                maxBytes={maxJsonBytes}
                                file={file}
                                disabled={busy}
                                loading={reading}
                                onSelect={(next) => void readFile(next)}
                                onRemove={() => {
                                    setFile(null);
                                    setFileError("");
                                    setJson("");
                                    setError("");
                                }}
                            />
                        )}
                    </Field>
                    <Field label={t("JSON data")} error={error}>
                        {(id) => (
                            <Textarea
                                id={id}
                                className="min-h-64 font-mono"
                                value={json}
                                disabled={busy || reading}
                                onChange={(event) => {
                                    setJson(event.target.value);
                                    setFile(null);
                                    setFileError("");
                                    setError("");
                                }}
                            />
                        )}
                    </Field>
                    <Button type="submit" loading={busy} disabled={reading}>
                        {t("Import")}
                    </Button>
                </form>
            </Dialog>
        </>
    );
}
