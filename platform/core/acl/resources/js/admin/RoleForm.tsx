import { SearchInput } from "@admin/components/search-input";
import { useConfirmDialog } from "@admin/components/confirm-dialog";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Input, Textarea } from "@admin/components/ui/input";
import { Checkbox } from "@admin/components/ui/checkbox";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Field } from "@admin/components/field";
import { useAdminTranslation } from "@admin/lib/translate";

type Props = {
    role: {
        id: number;
        name: string;
        description: string | null;
        is_default: boolean;
        permissions: Record<string, boolean>;
    } | null;
    flags: { value: string; label: string; parent: string | null }[];
    urls: { index: string; submit: string; duplicate: string | null };
};
export default function RoleForm({ role, flags, urls }: Props) {
    const t = useAdminTranslation();
    const { confirm, confirmationDialog } = useConfirmDialog();
    const [search, setSearch] = useState("");
    const [collapsed, setCollapsed] = useState<string[]>([]);
    const form = useForm({
        name: role?.name ?? "",
        description: role?.description ?? "",
        is_default: role?.is_default ? 1 : 0,
        flags: Object.entries(role?.permissions ?? {})
            .filter(([, enabled]) => enabled)
            .map(([flag]) => flag),
        submitter: "apply",
    });
    const roots = flags.filter(
        (flag) =>
            !flag.parent ||
            !flags.some((parent) => parent.value === flag.parent),
    );
    const children = (parent: string) =>
        flags.filter((flag) => flag.parent === parent);
    function descendants(value: string, seen: string[] = []): string[] {
        if (seen.includes(value)) return [];
        return [
            value,
            ...children(value).flatMap((child) =>
                descendants(child.value, [...seen, value]),
            ),
        ];
    }
    function toggle(values: string[], enabled: boolean) {
        form.setData(
            "flags",
            enabled
                ? [...new Set([...form.data.flags, ...values])]
                : form.data.flags.filter((flag) => !values.includes(flag)),
        );
    }
    function permissionTree(parent: string, depth = 0) {
        if (depth > 10) return null;
        return children(parent).map((flag) => (
            <li key={flag.value} className="min-w-0">
                <label className="flex items-center gap-1 py-1 text-sm">
                    <Checkbox
                        checked={form.data.flags.includes(flag.value)}
                        onCheckedChange={(checked) =>
                            toggle(descendants(flag.value), checked === true)
                        }
                    />
                    {flag.label}
                </label>
                {children(flag.value).length > 0 && (
                    <ul className="ml-3 border-l pl-3">
                        {permissionTree(flag.value, depth + 1)}
                    </ul>
                )}
            </li>
        ));
    }
    const visibleRoots = roots.filter((root) =>
        descendants(root.value).some((value) => {
            const flag = flags.find((entry) => entry.value === value)!;
            return `${flag.label} ${flag.value}`
                .toLowerCase()
                .includes(search.toLowerCase());
        }),
    );
    useUnsavedChanges(form.isDirty, form.processing);
    return (
        <AdminLayout
            title={t(role ? "Edit role" : "Create role")}
            actions={
                <Button asChild variant="outline">
                    <Link href={urls.index}>{t("Back")}</Link>
                </Button>
            }
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    const submitter = (event.nativeEvent as SubmitEvent)
                        .submitter as HTMLButtonElement | null;
                    form.transform((data) => ({
                        ...data,
                        submitter: submitter?.value || "apply",
                    }));
                    form.post(urls.submit, {
                        onSuccess: () => form.setDefaults(form.data),
                    });
                }}
                className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]"
            >
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Role information")}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 p-5">
                            <Field
                                label={t("Name")}
                                error={form.errors.name}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        required
                                        maxLength={120}
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                "name",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                label={t("Description")}
                                error={form.errors.description}
                            >
                                {(id) => (
                                    <Textarea
                                        id={id}
                                        maxLength={255}
                                        value={form.data.description}
                                        onChange={(event) =>
                                            form.setData(
                                                "description",
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={!!form.data.is_default}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            "is_default",
                                            checked === true ? 1 : 0,
                                        )
                                    }
                                />
                                {t("Default role")}
                            </label>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Permissions")}</CardTitle>
                        </CardHeader>
                        <CardContent className="p-5">
                            <SearchInput
                                onClear={() => {
                                    setSearch("");
                                }}
                                aria-label={t("Search permissions")}
                                placeholder={t("Search permissions")}
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                className="mb-4 max-w-sm"
                            />
                            {form.errors.flags && (
                                <p
                                    role="alert"
                                    className="mb-4 text-sm text-destructive"
                                >
                                    {form.errors.flags}
                                </p>
                            )}
                            <div className="mb-4 flex flex-wrap items-center gap-3 border-b pb-3">
                                <label className="flex items-center gap-1 text-sm font-medium">
                                    <Checkbox
                                        checked={
                                            flags.length > 0 &&
                                            flags.every((flag) =>
                                                form.data.flags.includes(
                                                    flag.value,
                                                ),
                                            )
                                        }
                                        indeterminate={
                                            flags.some((flag) =>
                                                form.data.flags.includes(
                                                    flag.value,
                                                ),
                                            ) &&
                                            !flags.every((flag) =>
                                                form.data.flags.includes(
                                                    flag.value,
                                                ),
                                            )
                                        }
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                flags.map((flag) => flag.value),
                                                checked === true,
                                            )
                                        }
                                    />
                                    {t("Select all")}
                                </label>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        setCollapsed(
                                            roots.map((root) => root.value),
                                        )
                                    }
                                >
                                    {t("Collapse all")}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setCollapsed([])}
                                >
                                    {t("Expand all")}
                                </Button>
                                <span className="ml-auto text-xs tabular-nums text-muted-foreground">
                                    {
                                        form.data.flags.filter((flag) =>
                                            flags.some(
                                                (entry) => entry.value === flag,
                                            ),
                                        ).length
                                    }{" "}
                                    / {flags.length} {t("selected")}
                                </span>
                            </div>
                            <div className="space-y-3">
                                {visibleRoots.map((root) => {
                                    const groupFlags = descendants(root.value);
                                    const count = groupFlags.filter((flag) =>
                                        form.data.flags.includes(flag),
                                    ).length;
                                    return (
                                        <section
                                            key={root.value}
                                            className="overflow-hidden rounded-md border"
                                        >
                                            <div className="flex items-center gap-2 bg-muted/40 px-3 py-2">
                                                <label className="flex flex-1 items-center gap-1 text-sm font-semibold">
                                                    <Checkbox
                                                        checked={
                                                            count ===
                                                            groupFlags.length
                                                        }
                                                        indeterminate={
                                                            count > 0 &&
                                                            count <
                                                                groupFlags.length
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggle(
                                                                groupFlags,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {root.label}
                                                </label>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    aria-expanded={
                                                        !collapsed.includes(
                                                            root.value,
                                                        )
                                                    }
                                                    aria-label={`${t(collapsed.includes(root.value) ? "Expand" : "Collapse")} ${root.label}`}
                                                    onClick={() =>
                                                        setCollapsed(
                                                            (values) =>
                                                                values.includes(
                                                                    root.value,
                                                                )
                                                                    ? values.filter(
                                                                          (
                                                                              value,
                                                                          ) =>
                                                                              value !==
                                                                              root.value,
                                                                      )
                                                                    : [
                                                                          ...values,
                                                                          root.value,
                                                                      ],
                                                        )
                                                    }
                                                >
                                                    {count}/{groupFlags.length}{" "}
                                                    {collapsed.includes(
                                                        root.value,
                                                    )
                                                        ? "+"
                                                        : "−"}
                                                </Button>
                                            </div>
                                            {(!collapsed.includes(root.value) ||
                                                search) && (
                                                <ul className="grid gap-x-5 px-4 py-3 sm:grid-cols-2 lg:grid-cols-3">
                                                    {permissionTree(root.value)}
                                                </ul>
                                            )}
                                        </section>
                                    );
                                })}
                                {!visibleRoots.length && (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        {t("No permissions found")}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <Card className="xl:sticky xl:top-20">
                    <CardHeader>
                        <CardTitle>{t("Publish")}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                name="submitter"
                                value="apply"
                                loading={form.processing}
                            >
                                {t("Save")}
                            </Button>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                                name="submitter"
                                value="save"
                            >
                                {t("Save & exit")}
                            </Button>
                        </div>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {t(
                                "Review the permissions before saving this role.",
                            )}
                        </p>
                        <div className="border-t pt-3">
                            {urls.duplicate ? (
                                <Button
                                    variant="outline"
                                    onClick={async () => {
                                        if (
                                            form.isDirty &&
                                            !(await confirm(
                                                t("Discard unsaved changes?"),
                                            ))
                                        )
                                            return;
                                        if (urls.duplicate)
                                            router.post(urls.duplicate);
                                    }}
                                >
                                    {t("Duplicate")}
                                </Button>
                            ) : (
                                <span />
                            )}
                        </div>
                    </CardContent>
                </Card>
            </form>
            {confirmationDialog}
        </AdminLayout>
    );
}
