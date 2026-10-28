import { useConfirmDialog } from "@admin/components/confirm-dialog";
import { Field } from "@admin/components/field";
import { notify } from "@admin/components/toast";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@admin/components/ui/table";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { Head, router } from "@inertiajs/react";
import axios from "axios";
import {
    CheckCircle2,
    Download,
    FileSpreadsheet,
    UploadCloud,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { downloadError, downloadFilename } from "../lib/download";
type Props = {
    title: string;
    upload: string;
    validate: string;
    submit: string;
    example?: string;
    chunkSize: number;
    maxChunkSize?: number;
    examples?: Record<string, string>[];
    extensions: string[];
    columns: {
        name: string;
        label: string;
        description?: string;
        required: boolean;
    }[];
};
export default function Import({
    title,
    upload,
    validate,
    submit,
    example,
    chunkSize,
    maxChunkSize = 10000,
    examples = [],
    extensions,
    columns,
}: Props) {
    const t = useAdminTranslation();
    const { confirm, confirmationDialog } = useConfirmDialog();
    const [batchSize, setBatchSize] = useState(chunkSize);
    const validBatch =
        Number.isInteger(batchSize) &&
        batchSize >= 1 &&
        batchSize <= maxChunkSize;
    const [file, setFile] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    const [message, setMessage] = useState("");
    const [failures, setFailures] = useState<string[]>([]);
    const [format, setFormat] = useState("csv");
    const controller = useRef<AbortController | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const [stage, setStage] = useState("");
    function chooseFile(next: File | null) {
        setProgress(0);
        setFailures([]);
        setMessage("");
        setStage("");
        if (
            next &&
            !extensions
                .map((extension) => extension.replace(/^\./, "").toLowerCase())
                .includes(next.name.split(".").pop()?.toLowerCase() ?? "")
        ) {
            setFile(null);
            setMessage(t("Unsupported file type"));
            return;
        }
        setFile(next);
    }
    useEffect(() => () => controller.current?.abort(), []);
    useEffect(() => {
        if (!busy) return;
        const unload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", unload);
        const stop = router.on("before", (event) => {
            if (event.detail.visit.method !== "get") return;
            event.preventDefault();
            const visit = event.detail.visit;
            void confirm(
                t(
                    "Stop importing and leave? Records already imported are retained.",
                ),
            ).then((accepted) => {
                if (!accepted) return;
                controller.current?.abort();
                stop();
                router.visit(visit.url, visit);
            });
        });
        return () => {
            window.removeEventListener("beforeunload", unload);
            stop();
        };
    }, [busy, t, confirm]);
    async function post(url: string, data: unknown) {
        const response = await axios.post(url, data, {
            signal: controller.current?.signal,
        });
        if (response.data.error) throw new Error(response.data.message);
        setMessage(response.data.message ?? "");
        return response.data.data;
    }
    async function start() {
        if (!file || busy || !validBatch) return;
        controller.current = new AbortController();
        let writesStarted = false;
        setBusy(true);
        setProgress(0);
        setFailures([]);
        try {
            setStage(t("Uploading file"));
            let filename = "";
            const uuid = crypto.randomUUID();
            const size = 1024 * 1024;
            const chunks = Math.max(1, Math.ceil(file.size / size));
            for (let index = 0; index < chunks; index++) {
                const data = new FormData();
                data.append(
                    "file",
                    file.slice(index * size, (index + 1) * size),
                    file.name,
                );
                data.append("dzuuid", uuid);
                data.append("dzchunkindex", String(index));
                data.append("dztotalchunkcount", String(chunks));
                const result = await post(upload, data);
                if (result.file_name) filename = result.file_name;
                setProgress(Math.round(((index + 1) / chunks) * 20));
            }
            if (!filename) throw new Error(t("Upload failed"));
            setStage(t("Validating data"));
            let offset = 0,
                total = 0;
            while (true) {
                const result = await post(validate, {
                    file_name: filename,
                    offset,
                    limit: batchSize,
                });
                filename = result.file_name;
                total = result.total;
                if (result.errors?.length) {
                    setFailures(result.errors.map(String));
                    throw new Error(
                        t("Validation failed. No data was imported."),
                    );
                }
                if (!result.count) break;
                offset = result.offset + batchSize;
                setProgress(
                    20 +
                        Math.min(
                            30,
                            Math.round((offset / Math.max(total, 1)) * 30),
                        ),
                );
            }
            setStage(t("Importing records"));
            writesStarted = true;
            offset = 0;
            let imported = 0;
            const failed: string[] = [];
            while (true) {
                const result = await post(submit, {
                    file_name: filename,
                    offset,
                    limit: batchSize,
                    total: imported,
                });
                imported = result.total;
                if (result.failures?.length)
                    failed.push(
                        ...result.failures.map(
                            (failure: {
                                row: number;
                                attribute: string;
                                errors: string[];
                            }) =>
                                `#${failure.row} ${failure.attribute}: ${failure.errors.join(", ")}`,
                        ),
                    );
                setFailures([...failed]);
                if (!result.count) break;
                offset = result.offset + batchSize;
                setProgress(
                    50 +
                        Math.min(
                            49,
                            Math.round((offset / Math.max(total, 1)) * 50),
                        ),
                );
            }
            setProgress(100);
            const completion = failed.length
                ? t("Import completed with errors")
                : t("Import complete");
            setStage(completion);
            notify(completion, failed.length > 0);
            setFailures(failed);
        } catch (error) {
            const errorMessage = !controller.current.signal.aborted
                ? axios.isAxiosError(error)
                    ? (error.response?.data?.message ?? error.message)
                    : error instanceof Error
                      ? error.message
                      : t("Import failed")
                : t("Stopped. Records already imported are retained.");
            setStage(
                t(
                    controller.current.signal.aborted
                        ? "Stopped"
                        : "Import failed",
                ),
            );
            setMessage(errorMessage);
            notify(errorMessage, true);
        } finally {
            if (writesStarted) setFile(null);
            setBusy(false);
        }
    }
    async function downloadExample() {
        if (!example) return;
        try {
            const response = await axios.post(
                example,
                { format },
                { responseType: "blob" },
            );
            if (
                String(response.headers["content-type"] ?? "").includes(
                    "json",
                ) ||
                !response.headers["content-disposition"]
            ) {
                throw new Error(
                    await downloadError(response.data, t("Download failed")),
                );
            }
            const url = URL.createObjectURL(response.data);
            const a = document.createElement("a");
            a.href = url;
            a.download = downloadFilename(
                response.headers["content-disposition"],
                `example.${format}`,
            );
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (error) {
            const message = axios.isAxiosError(error)
                ? await downloadError(
                      error.response?.data,
                      error.message || t("Download failed"),
                  )
                : error instanceof Error
                  ? error.message
                  : t("Download failed");
            setMessage(message);
            notify(message, true);
        }
    }
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="grid min-w-0 grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="min-w-0 space-y-5">
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader>
                            <CardTitle>{t("Import file")}</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    "Upload your file. All rows are validated before importing.",
                                )}
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div
                                className="flex min-h-52 flex-col items-center justify-center gap-3 rounded-md border-2 border-dashed bg-muted/20 p-6 text-center focus-within:border-primary"
                                onDragOver={(event) => {
                                    event.preventDefault();
                                }}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    if (!busy)
                                        chooseFile(
                                            event.dataTransfer.files[0] ?? null,
                                        );
                                }}
                            >
                                <UploadCloud
                                    size={36}
                                    strokeWidth={1.25}
                                    className="text-muted-foreground"
                                />
                                <p className="text-sm font-medium">
                                    {t(
                                        "Drop your file here or browse to upload",
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {extensions
                                        .map((extension) =>
                                            extension
                                                .replace(/^\./, "")
                                                .toUpperCase(),
                                        )
                                        .join(", ")}
                                </p>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => fileInput.current?.click()}
                                >
                                    {t("Choose file")}
                                </Button>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    aria-label={t("Import file")}
                                    className="sr-only"
                                    disabled={busy}
                                    accept={extensions
                                        .map(
                                            (extension) =>
                                                `.${extension.replace(/^\./, "")}`,
                                        )
                                        .join(",")}
                                    onChange={(event) => {
                                        chooseFile(
                                            event.target.files?.[0] ?? null,
                                        );
                                        event.target.value = "";
                                    }}
                                />
                            </div>
                            <Field
                                label={t("Chunk size")}
                                description={t(
                                    "Number of records processed per request.",
                                )}
                                error={
                                    !validBatch
                                        ? t(
                                              "Enter a whole number from 1 to :max.",
                                              { max: maxChunkSize },
                                          )
                                        : undefined
                                }
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="number"
                                        min={1}
                                        max={maxChunkSize}
                                        step={1}
                                        value={batchSize}
                                        disabled={busy}
                                        onChange={(event) =>
                                            setBatchSize(
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            {file && (
                                <div className="flex items-center gap-3 rounded-md border p-3">
                                    <FileSpreadsheet
                                        size={22}
                                        className="shrink-0 text-primary"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {file.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {(file.size / 1024).toFixed(1)} KB
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t("Remove file")}
                                        disabled={busy}
                                        onClick={() => chooseFile(null)}
                                    >
                                        <X size={16} />
                                    </Button>
                                </div>
                            )}
                            {(busy || progress > 0) && (
                                <div className="space-y-2 rounded-md border bg-muted/20 p-4">
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <span className="flex items-center gap-2">
                                            {progress === 100 && (
                                                <CheckCircle2
                                                    size={16}
                                                    className="text-primary"
                                                />
                                            )}
                                            {stage}
                                        </span>
                                        <span className="tabular-nums">
                                            {progress}%
                                        </span>
                                    </div>
                                    <progress
                                        max={100}
                                        value={progress}
                                        className="h-2 w-full accent-primary"
                                        aria-label={t("Import progress")}
                                    />
                                </div>
                            )}
                            {message && (
                                <p
                                    role="status"
                                    className="rounded-md border bg-muted/20 p-3 text-sm"
                                >
                                    {message}
                                </p>
                            )}
                            {failures.length > 0 && (
                                <div
                                    role="alert"
                                    className="rounded-md border border-destructive/30 p-4"
                                >
                                    <h3 className="mb-2 text-sm font-semibold text-destructive">
                                        {t("Import errors")}
                                    </h3>
                                    <ul className="max-h-60 list-inside list-disc space-y-1 overflow-auto text-xs text-destructive">
                                        {failures.map((failure, index) => (
                                            <li key={index}>{failure}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardContent>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/20 px-5 py-4">
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    "Check the column reference before importing.",
                                )}
                            </p>
                            <div className="flex gap-2">
                                {busy && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            controller.current?.abort()
                                        }
                                    >
                                        {t("Stop")}
                                    </Button>
                                )}
                                <Button
                                    disabled={!file || !validBatch}
                                    loading={busy}
                                    onClick={() => void start()}
                                >
                                    <UploadCloud size={16} />
                                    {t("Validate and import")}
                                </Button>
                            </div>
                        </div>
                    </Card>
                    {examples.length > 0 && (
                        <Card className="min-w-0 overflow-hidden">
                            <CardHeader>
                                <CardTitle>{t("Example data")}</CardTitle>
                            </CardHeader>
                            <div className="w-full min-w-0 overflow-x-auto">
                                <Table className="w-full text-left text-sm">
                                    <TableHeader className="border-y bg-muted/30">
                                        <TableRow>
                                            {columns.map((column) => (
                                                <TableHead
                                                    key={column.name}
                                                    className="px-4 py-3"
                                                >
                                                    {column.label}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="divide-y">
                                        {examples.map((row, index) => (
                                            <TableRow key={index}>
                                                {columns.map((column) => (
                                                    <TableCell
                                                        key={column.name}
                                                        className="max-w-sm break-words px-4 py-3"
                                                    >
                                                        {row[column.name] ?? ""}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </Card>
                    )}
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader>
                            <CardTitle>{t("Column reference")}</CardTitle>
                        </CardHeader>
                        <div className="w-full min-w-0 overflow-x-auto">
                            <Table className="w-full text-left text-sm">
                                <TableHeader className="border-y bg-muted/30">
                                    <TableRow>
                                        <TableHead className="px-5 py-3">
                                            {t("Column")}
                                        </TableHead>
                                        <TableHead className="px-5 py-3">
                                            {t("Description")}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {columns.map((column) => (
                                        <TableRow key={column.name}>
                                            <TableCell className="px-5 py-3 align-top">
                                                <span className="font-mono text-xs">
                                                    {column.name}
                                                </span>
                                                {column.required && (
                                                    <span className="ml-2 rounded bg-destructive/5 px-1.5 py-0.5 text-[10px] font-medium text-destructive">
                                                        {t("Required")}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-5 py-3">
                                                <p className="font-medium">
                                                    {column.label}
                                                </p>
                                                {column.description && (
                                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                        {column.description}
                                                    </p>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>
                </div>
                {example && (
                    <Card className="min-w-0 xl:sticky xl:top-20">
                        <CardHeader>
                            <CardTitle>{t("Download example")}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {t(
                                    "Use the example file as a template for your import.",
                                )}
                            </p>
                            <Select
                                aria-label={t("Example format")}
                                value={format}
                                disabled={busy}
                                onValueChange={(value) => setFormat(value)}
                            >
                                <option value="csv">CSV</option>
                                <option value="xlsx">Excel (.xlsx)</option>
                            </Select>
                            <Button
                                variant="outline"
                                className="w-full"
                                disabled={busy}
                                onClick={() => void downloadExample()}
                            >
                                <Download size={16} />
                                {t("Download example")}
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
            {confirmationDialog}
        </AdminLayout>
    );
}
