import {
    ContentResourceIndex,
    type ContentIndexProps,
} from "@admin/components/content-resource-index";
import { Button } from "@admin/components/ui/button";
import { Dialog } from "@admin/components/ui/dialog";
import { parseJsonImport } from "@admin/lib/json-import";
import { Input, Textarea } from "@admin/components/ui/input";
import { Field } from "@admin/components/field";
import { useState } from "react";
import { router } from "@inertiajs/react";
import { useAdminTranslation } from "@admin/lib/translate";
export default function FieldGroupsIndex(
    props: ContentIndexProps & {
        urls: ContentIndexProps["urls"] & { import: string; export: string };
    },
) {
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const [json, setJson] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    async function readFile(file?: File) {
        if (!file || busy) return;
        setBusy(true);
        setError("");
        try {
            if (file.size > 2 * 1024 * 1024)
                throw new Error("File is too large");
            const text = await file.text();
            parseJsonImport(text, 2 * 1024 * 1024, 100);
            setJson(text);
        } catch (error) {
            setError(
                t(error instanceof Error ? error.message : "Invalid JSON"),
            );
        } finally {
            setBusy(false);
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
                onOpenChange={(value) => !busy && setOpen(value)}
                title={t("Import")}
            >
                <form
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => {
                        event.preventDefault();
                        void readFile(event.dataTransfer.files[0]);
                    }}
                    onSubmit={(event) => {
                        event.preventDefault();
                        try {
                            const data = parseJsonImport(
                                json,
                                2 * 1024 * 1024,
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
                    <Field label={t("JSON file")}>
                        {(id) => (
                            <Input
                                id={id}
                                type="file"
                                accept=".json,application/json"
                                disabled={busy}
                                onChange={(event) => {
                                    void readFile(event.target.files?.[0]);
                                    event.target.value = "";
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
                                disabled={busy}
                                onChange={(event) =>
                                    setJson(event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Button type="submit" loading={busy} className="mt-4">
                        {t("Import")}
                    </Button>
                </form>
            </Dialog>
        </>
    );
}
