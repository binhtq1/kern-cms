import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { Head, useForm, router } from "@inertiajs/react";
import { useState } from "react";
import { Pencil, Trash2, Plus } from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
} from "@admin/components/ui/card";
import { Dialog } from "@admin/components/ui/dialog";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Field } from "@admin/components/field";
import { Badge } from "@admin/components/ui/badge";
type Language = {
    lang_id?: number;
    lang_name: string;
    lang_code: string;
    lang_locale: string;
    lang_flag: string;
    lang_order: number;
    lang_is_rtl: boolean;
    lang_is_default?: boolean;
    delete_url?: string;
};
type Settings = {
    language_hide_default: number;
    language_display: string;
    language_switcher_display: string;
    language_hide_languages: string[];
    language_show_default_item_if_current_version_not_existed: number;
    language_auto_detect_user_language: number;
};
type Props = {
    languages: Language[];
    presets: Omit<Language, "lang_order">[];
    codes: string[];
    locales: string[];
    flags: Record<string, string>;
    settings: Settings;
    permissions: { create: boolean; edit: boolean; delete: boolean };
    urls: {
        index: string;
        create: string;
        update: string;
        default: string;
        settings: string;
    };
};
const blank: Language = {
    lang_name: "",
    lang_code: "en_US",
    lang_locale: "en",
    lang_flag: "us",
    lang_order: 0,
    lang_is_rtl: false,
};
export default function LanguagesIndex({
    languages,
    presets,
    codes,
    locales,
    flags,
    settings,
    permissions,
    urls,
}: Props) {
    const t = useAdminTranslation();
    const [preset, setPreset] = useState("");
    const [open, setOpen] = useState(false);
    const [target, setTarget] = useState<Language | null>(null);
    const [busy, setBusy] = useState(false);
    const form = useForm<Language>(blank);
    const settingsForm = useForm(settings);
    useUnsavedChanges(
        settingsForm.isDirty || (open && form.isDirty),
        form.processing || settingsForm.processing,
    );
    function edit(item?: Language) {
        setPreset("");
        form.clearErrors();
        form.setDefaults(item ? { ...item } : blank);
        form.setData(item ? { ...item } : blank);
        setOpen(true);
    }
    return (
        <AdminLayout
            title={t("Languages")}
            actions={
                permissions.create && (
                    <Button onClick={() => edit()}>
                        <Plus />
                        {t("Add language")}
                    </Button>
                )
            }
        >
            <Head title={t("Languages")} />
            <Card>
                <div className="overflow-x-auto">
                    <Table className="w-full text-left text-sm">
                        <TableHeader className="bg-muted/40 text-muted-foreground">
                            <TableRow>
                                {[
                                    "Name",
                                    "Code",
                                    "Locale",
                                    "Order",
                                    "Actions",
                                ].map((label) => (
                                    <TableHead
                                        key={label}
                                        className="px-4 py-3"
                                    >
                                        {t(label)}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {languages.map((item) => (
                                <TableRow
                                    key={item.lang_id}
                                    className="border-t"
                                >
                                    <TableCell className="px-4 py-3 font-medium">
                                        {item.lang_name}{" "}
                                        {item.lang_is_default && (
                                            <Badge>{t("Default")}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 font-mono text-xs">
                                        {item.lang_code}
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
                                        {item.lang_locale}
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
                                        {item.lang_order}
                                    </TableCell>
                                    <TableCell className="flex flex-wrap gap-1 px-4 py-2">
                                        {permissions.edit && (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t("Edit")}
                                                    onClick={() => edit(item)}
                                                >
                                                    <Pencil />
                                                </Button>
                                                {!item.lang_is_default && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                urls.default,
                                                                {
                                                                    lang_id:
                                                                        item.lang_id,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {t("Set default")}
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                        {permissions.delete && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t("Delete")}
                                                onClick={() => setTarget(item)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {!languages.length && (
                    <p className="p-8 text-center text-muted-foreground">
                        {t("No results found")}
                    </p>
                )}
            </Card>
            {permissions.edit && (
                <Card className="mt-5">
                    <CardHeader>
                        <CardTitle>{t("Language settings")}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                settingsForm.post(urls.settings, {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        settingsForm.setDefaults(
                                            settingsForm.data,
                                        ),
                                });
                            }}
                        >
                            {(
                                [
                                    "language_hide_default",
                                    "language_show_default_item_if_current_version_not_existed",
                                    "language_auto_detect_user_language",
                                ] as const
                            ).map((name) => (
                                <Field
                                    key={name}
                                    label={t(
                                        name === "language_hide_default"
                                            ? "Hide default language in URL"
                                            : name ===
                                                "language_auto_detect_user_language"
                                              ? "Detect visitor language"
                                              : "Show default content when translation is missing",
                                    )}
                                    error={settingsForm.errors[name]}
                                >
                                    {(id) => (
                                        <Checkbox
                                            id={id}
                                            checked={Boolean(
                                                Number(settingsForm.data[name]),
                                            )}
                                            onCheckedChange={(checked) =>
                                                settingsForm.setData(
                                                    name,
                                                    checked === true ? 1 : 0,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            ))}
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field label={t("Language display")}>
                                    {(id) => (
                                        <Select
                                            id={id}
                                            value={
                                                settingsForm.data
                                                    .language_display
                                            }
                                            onValueChange={(value) =>
                                                settingsForm.setData(
                                                    "language_display",
                                                    value,
                                                )
                                            }
                                        >
                                            {["all", "flag", "name"].map(
                                                (value) => (
                                                    <option key={value}>
                                                        {value}
                                                    </option>
                                                ),
                                            )}
                                        </Select>
                                    )}
                                </Field>
                                <Field label={t("Switcher display")}>
                                    {(id) => (
                                        <Select
                                            id={id}
                                            value={
                                                settingsForm.data
                                                    .language_switcher_display
                                            }
                                            onValueChange={(value) =>
                                                settingsForm.setData(
                                                    "language_switcher_display",
                                                    value,
                                                )
                                            }
                                        >
                                            {["dropdown", "list"].map(
                                                (value) => (
                                                    <option key={value}>
                                                        {value}
                                                    </option>
                                                ),
                                            )}
                                        </Select>
                                    )}
                                </Field>
                            </div>
                            <Field label={t("Hidden languages")} group>
                                {() => (
                                    <div className="flex flex-wrap gap-4">
                                        {languages
                                            .filter(
                                                (item) => !item.lang_is_default,
                                            )
                                            .map((item) => {
                                                const value = String(
                                                    item.lang_id,
                                                );
                                                return (
                                                    <label
                                                        key={value}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            checked={settingsForm.data.language_hide_languages
                                                                .map(String)
                                                                .includes(
                                                                    value,
                                                                )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                settingsForm.setData(
                                                                    "language_hide_languages",
                                                                    checked ===
                                                                        true
                                                                        ? [
                                                                              ...settingsForm
                                                                                  .data
                                                                                  .language_hide_languages,
                                                                              value,
                                                                          ]
                                                                        : settingsForm.data.language_hide_languages.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  String(
                                                                                      id,
                                                                                  ) !==
                                                                                  value,
                                                                          ),
                                                                )
                                                            }
                                                        />
                                                        {item.lang_name}
                                                    </label>
                                                );
                                            })}
                                    </div>
                                )}
                            </Field>
                            <Button
                                type="submit"
                                loading={settingsForm.processing}
                            >
                                {t("Save")}
                            </Button>
                            {Object.values(settingsForm.errors).map((error) => (
                                <p
                                    key={error}
                                    className="text-destructive"
                                    role="alert"
                                >
                                    {error}
                                </p>
                            ))}
                        </form>
                    </CardContent>
                </Card>
            )}
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title={t(form.data.lang_id ? "Edit language" : "Add language")}
            >
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            form.data.lang_id ? urls.update : urls.create,
                            {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            },
                        );
                    }}
                >
                    {!form.data.lang_id && (
                        <Field label={t("Choose a language")}>
                            {(id) => (
                                <Select
                                    id={id}
                                    value={preset}
                                    onValueChange={(value) => {
                                        setPreset(value);
                                        const selected = presets.find(
                                            (item) => item.lang_code === value,
                                        );
                                        if (selected) {
                                            form.setData({
                                                ...form.data,
                                                ...selected,
                                            });
                                            form.clearErrors();
                                        }
                                    }}
                                >
                                    <option value="">
                                        {t("Select language")}
                                    </option>
                                    {presets.map((item) => (
                                        <option
                                            key={item.lang_code}
                                            value={item.lang_code}
                                        >
                                            {item.lang_name} - {item.lang_code}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </Field>
                    )}
                    <Field
                        label={t("Name")}
                        required
                        error={form.errors.lang_name}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={form.data.lang_name}
                                onChange={(event) =>
                                    form.setData(
                                        "lang_name",
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        )}
                    </Field>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {(
                            ["lang_code", "lang_locale", "lang_flag"] as const
                        ).map((name) => (
                            <Field
                                key={name}
                                label={t(
                                    name === "lang_code"
                                        ? "Code"
                                        : name === "lang_locale"
                                          ? "Locale"
                                          : "Flag",
                                )}
                                error={form.errors[name]}
                            >
                                {(id) => (
                                    <Select
                                        id={id}
                                        value={form.data[name]}
                                        onValueChange={(value) =>
                                            form.setData(name, value)
                                        }
                                    >
                                        {(name === "lang_code"
                                            ? codes
                                            : name === "lang_locale"
                                              ? locales
                                              : Object.keys(flags)
                                        ).map((value) => (
                                            <option key={value} value={value}>
                                                {name === "lang_flag"
                                                    ? flags[value]
                                                    : value}
                                            </option>
                                        ))}
                                    </Select>
                                )}
                            </Field>
                        ))}
                        <Field
                            label={t("Order")}
                            error={form.errors.lang_order}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min={0}
                                    value={form.data.lang_order}
                                    onChange={(event) =>
                                        form.setData(
                                            "lang_order",
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>
                    <Field label={t("Right to left")}>
                        {(id) => (
                            <Checkbox
                                id={id}
                                checked={form.data.lang_is_rtl}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        "lang_is_rtl",
                                        checked === true,
                                    )
                                }
                            />
                        )}
                    </Field>
                    <Button type="submit" loading={form.processing}>
                        {t("Save")}
                    </Button>
                </form>
            </Dialog>
            <Dialog
                open={Boolean(target)}
                onOpenChange={(open) => !busy && !open && setTarget(null)}
                title={t("Delete language")}
                description={target?.lang_name}
                size="sm"
            >
                <p className="text-sm text-muted-foreground">
                    {t("This action cannot be undone.")}
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        onClick={() => setTarget(null)}
                        disabled={busy}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button
                        variant="destructive"
                        loading={busy}
                        onClick={() => {
                            if (!target?.delete_url) return;
                            setBusy(true);
                            router.delete(target.delete_url, {
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
