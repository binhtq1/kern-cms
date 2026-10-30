import { Field } from "@admin/components/field";
import { Badge } from "@admin/components/ui/badge";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
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
import { Head, router, useForm } from "@inertiajs/react";
import { Download, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { TranslationNavigation } from "../components/translation-navigation";

type Locale = {
    locale: string;
    name: string;
    is_default: boolean;
    delete_url: string;
    download_url: string;
};

export default function LocalesIndex({
    existing,
    locales,
    urls,
}: {
    existing: Locale[];
    locales: Record<string, string>;
    urls: {
        create: string;
        index: string;
        admin: string;
        theme: string;
        locales: string;
    };
}) {
    const t = useAdminTranslation();
    const [target, setTarget] = useState<Locale | null>(null);
    const [busy, setBusy] = useState(false);
    const form = useForm({ locale: Object.keys(locales)[0] ?? "en" });

    return (
        <AdminLayout title={t("Locales")}>
            <Head title={t("Locales")} />
            <TranslationNavigation active="locales" urls={urls} />
            <div className="grid gap-5 lg:grid-cols-[minmax(18rem,0.7fr)_minmax(0,1.3fr)] lg:items-start">
                <Card>
                    <CardHeader>
                        <CardTitle>{t("Add locale")}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(urls.create, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Field
                                label={t("Locale")}
                                error={form.errors.locale}
                            >
                                {(id) => (
                                    <Select
                                        id={id}
                                        value={form.data.locale}
                                        onValueChange={(value) =>
                                            form.setData("locale", value)
                                        }
                                    >
                                        {Object.entries(locales).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </Select>
                                )}
                            </Field>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    className="w-full sm:w-auto"
                                    loading={form.processing}
                                >
                                    <Plus aria-hidden="true" />
                                    {t("Add locale")}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0">
                        <CardTitle>{t("Available locales")}</CardTitle>
                        <Badge variant="outline" className="tabular-nums">
                            {existing.length}
                            <span className="sr-only">
                                {t("Available locales")}
                            </span>
                        </Badge>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <Table className="min-w-[34rem]">
                            <caption className="sr-only">
                                {t("Available locales")}
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t("Locale")}</TableHead>
                                    <TableHead className="w-28">
                                        {t("Code")}
                                    </TableHead>
                                    <TableHead className="w-48 text-right">
                                        <span className="sr-only">
                                            {t("Actions")}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {existing.map((item) => (
                                    <TableRow key={item.locale}>
                                        <TableCell className="font-medium">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span>{item.name}</span>
                                                {item.is_default && (
                                                    <Badge>
                                                        {t("Default")}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <code className="rounded bg-muted px-2 py-1 font-mono text-xs text-muted-foreground">
                                                {item.locale}
                                            </code>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2 whitespace-nowrap">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <a href={item.download_url}>
                                                        <Download aria-hidden="true" />
                                                        {t("Download")}
                                                    </a>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={
                                                        item.locale === "en"
                                                    }
                                                    onClick={() =>
                                                        setTarget(item)
                                                    }
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                    {t("Delete")}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {!existing.length && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            {t("No results found")}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            </div>
            <Dialog
                open={Boolean(target)}
                onOpenChange={(open) => !busy && !open && setTarget(null)}
                title={t("Delete locale")}
                description={target?.name}
                size="sm"
            >
                <p className="text-sm text-muted-foreground">
                    {t("This action cannot be undone.")}
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => setTarget(null)}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        loading={busy}
                        onClick={() => {
                            if (!target) return;
                            setBusy(true);
                            router.delete(target.delete_url, {
                                preserveScroll: true,
                                onSuccess: () => setTarget(null),
                                onFinish: () => setBusy(false),
                            });
                        }}
                    >
                        {t("Delete")}
                    </Button>
                </div>
            </Dialog>
        </AdminLayout>
    );
}
