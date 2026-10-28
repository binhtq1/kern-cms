import { notify } from "@admin/components/toast";
import { Head } from "@inertiajs/react";
import { useState } from "react";
import axios from "axios";
import AdminLayout from "@admin/layouts/admin-layout";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Radio, RadioGroup } from "@admin/components/ui/radio";
import { Download, FileSpreadsheet, FileText } from "lucide-react";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent, CardHeader } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";
import { downloadError, downloadFilename } from "../lib/download";
export default function Export({
    title,
    submit,
    available,
    columns,
    counters,
}: {
    title: string;
    submit: string;
    available: boolean;
    columns: { name: string; label: string; disabled: boolean }[];
    counters: { label: string; value: string | number }[];
}) {
    const t = useAdminTranslation();
    const [selected, setSelected] = useState(
        columns.map((column) => column.name),
    );
    const [format, setFormat] = useState("csv");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    async function download() {
        setBusy(true);
        setError("");
        try {
            const response = await axios.post(
                submit,
                { format, columns: selected },
                { responseType: "blob" },
            );
            if (
                String(response.headers["content-type"] ?? "").includes(
                    "json",
                ) ||
                !response.headers["content-disposition"]
            ) {
                throw new Error(
                    await downloadError(response.data, t("Export failed")),
                );
            }
            const url = URL.createObjectURL(response.data);
            const link = document.createElement("a");
            link.href = url;
            const disposition = String(
                response.headers["content-disposition"] ?? "",
            );
            link.download = downloadFilename(disposition, `export.${format}`);
            link.click();
            notify(t("Export complete"));
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (error) {
            const message = axios.isAxiosError(error)
                ? await downloadError(
                      error.response?.data,
                      error.message || t("Export failed"),
                  )
                : error instanceof Error
                  ? error.message
                  : t("Export failed");
            setError(message);
            notify(message, true);
        } finally {
            setBusy(false);
        }
    }
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <Card className="max-w-5xl">
                <CardHeader>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            "Choose the columns and file format for your export.",
                        )}
                    </p>
                </CardHeader>
                {available ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            void download();
                        }}
                    >
                        <CardContent className="space-y-6">
                            {counters.length > 0 && (
                                <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                    {counters.map((counter) => (
                                        <div
                                            key={counter.label}
                                            className="rounded-md bg-muted/50 p-4 text-center"
                                        >
                                            <dt className="text-xs text-muted-foreground">
                                                {counter.label}
                                            </dt>
                                            <dd className="mt-2 text-3xl font-semibold tabular-nums">
                                                {counter.value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-sm font-semibold">
                                    {t("Columns")}
                                </legend>
                                <div className="flex items-center justify-between gap-3 border-b pb-3">
                                    <label className="flex items-center gap-1 text-sm">
                                        <Checkbox
                                            disabled={
                                                busy ||
                                                columns.every(
                                                    (column) => column.disabled,
                                                )
                                            }
                                            checked={
                                                selected.length ===
                                                columns.length
                                            }
                                            indeterminate={
                                                selected.length >
                                                    columns.filter(
                                                        (column) =>
                                                            column.disabled,
                                                    ).length &&
                                                selected.length < columns.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? columns.map(
                                                              (column) =>
                                                                  column.name,
                                                          )
                                                        : columns
                                                              .filter(
                                                                  (column) =>
                                                                      column.disabled,
                                                              )
                                                              .map(
                                                                  (column) =>
                                                                      column.name,
                                                              ),
                                                )
                                            }
                                        />
                                        {t("Select all")}
                                    </label>
                                    <span className="text-xs tabular-nums text-muted-foreground">
                                        {selected.length} / {columns.length}{" "}
                                        {t("selected")}
                                    </span>
                                </div>
                                <div className="grid gap-x-5 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                                    {columns.map((column) => (
                                        <label
                                            key={column.name}
                                            className="flex items-center gap-1 text-sm"
                                        >
                                            <Checkbox
                                                checked={selected.includes(
                                                    column.name,
                                                )}
                                                disabled={
                                                    column.disabled || busy
                                                }
                                                onCheckedChange={(checked) =>
                                                    setSelected(
                                                        checked === true
                                                            ? [
                                                                  ...new Set([
                                                                      ...selected,
                                                                      column.name,
                                                                  ]),
                                                              ]
                                                            : selected.filter(
                                                                  (name) =>
                                                                      name !==
                                                                      column.name,
                                                              ),
                                                    )
                                                }
                                            />
                                            {column.label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend className="mb-3 text-sm font-semibold">
                                    {t("Format")}
                                </legend>
                                <RadioGroup
                                    className="grid max-w-xl gap-3 sm:grid-cols-2"
                                    name="export-format"
                                    value={format}
                                    disabled={busy}
                                    onValueChange={setFormat}
                                    aria-label={t("Format")}
                                >
                                    {[
                                        {
                                            value: "csv",
                                            label: "CSV",
                                            description: t(
                                                "Comma-separated values",
                                            ),
                                            Icon: FileText,
                                        },
                                        {
                                            value: "xlsx",
                                            label: "Excel (.xlsx)",
                                            description: t(
                                                "Spreadsheet workbook",
                                            ),
                                            Icon: FileSpreadsheet,
                                        },
                                    ].map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex cursor-pointer items-center gap-3 rounded-md border p-4 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5"
                                        >
                                            <Radio value={option.value} />
                                            <option.Icon
                                                size={22}
                                                className="text-muted-foreground"
                                            />
                                            <span className="text-sm font-medium">
                                                {option.label}
                                                <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                                    {option.description}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </RadioGroup>
                            </fieldset>
                            {error && (
                                <p
                                    role="alert"
                                    className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            )}
                        </CardContent>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/20 px-5 py-4">
                            <p className="text-xs text-muted-foreground">
                                {t("Your data will be downloaded as a file.")}
                            </p>
                            <Button
                                type="submit"
                                loading={busy}
                                disabled={!selected.length}
                            >
                                <Download size={16} />
                                {t("Export")}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <CardContent>
                        <div className="flex flex-col items-center gap-3 py-12 text-center text-muted-foreground">
                            <FileSpreadsheet size={36} strokeWidth={1.25} />
                            <p className="text-sm">{t("No data to export")}</p>
                        </div>
                    </CardContent>
                )}
            </Card>
        </AdminLayout>
    );
}
