import { Field } from "@admin/components/field";
import {
    LanguageSwitcher,
    type LanguageChoice,
} from "@admin/components/language-switcher";
import { SearchInput } from "@admin/components/search-input";
import { LoadingIndicator } from "@admin/components/loading-indicator";
import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import AdminLayout from "@admin/layouts/admin-layout";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { cn } from "@admin/lib/utils";
import { Head, Link, useForm } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { MenuTree } from "../components/menu-tree";
import { useMenuReferences } from "../hooks/use-menu-references";
import {
    referenceMenuItems,
    selectMenuReferences,
    type MenuReference,
} from "../lib/item-edit";
import { mergeSavedFields, reconcileMenuSave } from "../lib/save-state";
import {
    cancelMenuItem,
    descendants,
    moveMenuItem,
    serializeMenu,
    type MenuItem,
} from "../lib/tree";

type Option = { value: string; label: string };
type Reference = MenuReference;
export type ReferencePage = {
    data: Reference[];
    current_page: number;
    last_page: number;
    total: number;
    type: string;
};
type Props = {
    title: string;
    menu: {
        id?: number | string | null;
        name: string;
        status: string;
        locations: string[];
        nodes: MenuItem[];
    };
    statuses: Option[];
    locations: Option[];
    references: Reference[];
    referencePage: ReferencePage;
    referenceTypes: Option[];
    urls: { index: string; submit: string; references: string };
    iconImageEnabled: boolean;
    media: { list: string; upload: string } | null;
    language: {
        code: string;
        refFrom: string | null;
        choices: LanguageChoice[];
    } | null;
};

export default function MenuForm({
    title,
    menu,
    statuses,
    locations,
    referencePage,
    referenceTypes,
    urls,
    iconImageEnabled,
    media,
    language,
}: Props) {
    const t = useAdminTranslation();
    const form = useForm({
        name: menu.name,
        status: menu.status,
        locations: menu.locations,
        menu_nodes: "",
        deleted_nodes: "",
        language: language?.code ?? "",
        ref_from: language?.refFrom ?? "",
    });
    const [nodes, setNodes] = useState(menu.nodes);
    const nodeSummaries = useRef(new Map<string, HTMLElement>());
    const reparentedFocus = useRef<string | null>(null);
    useEffect(() => {
        const key = reparentedFocus.current;
        if (!key) return;
        const frame = requestAnimationFrame(() => {
            const summary = nodeSummaries.current.get(key);
            if (!summary?.isConnected) return;
            const details = summary.parentElement;
            if (details instanceof HTMLDetailsElement) details.open = true;
            summary.focus();
            reparentedFocus.current = null;
        });
        return () => cancelAnimationFrame(frame);
    }, [nodes]);
    const [nodeBaseline, setNodeBaseline] = useState(() =>
        JSON.stringify(menu.nodes),
    );
    const latestNodes = useRef(nodes);
    latestNodes.current = nodes;
    const latestForm = useRef(form.data);
    latestForm.current = form.data;
    const [selectedReferences, setSelectedReferences] = useState<Reference[]>(
        [],
    );
    const nodeEditSnapshots = useRef(new Map<string, MenuItem>());
    const {
        search,
        setSearch,
        referenceType,
        setReferenceType,
        referenceResults,
        referenceNumber,
        setReferenceNumber,
        referencesLoading,
        referencesError,
        setRetryReferences,
    } = useMenuReferences(
        referencePage,
        urls.references,
        menu.id,
        language?.code,
    );
    const visibleReferences = selectMenuReferences(
        selectedReferences,
        referenceResults.data,
        [
            ...selectedReferences.map((item) => item.value),
            ...referenceResults.data.map((item) => item.value),
        ],
    );

    const [linkTitle, setLinkTitle] = useState("");
    const [linkUrl, setLinkUrl] = useState("/");
    const [notice, setNotice] = useState("");
    function moveItem(key: string, parent: string, before?: string) {
        try {
            setNodes(moveMenuItem(nodes, key, parent, before));
            form.clearErrors("menu_nodes");
            setNotice(t("Item moved"));
            return true;
        } catch (error) {
            form.setError(
                "menu_nodes",
                t(
                    error instanceof Error
                        ? error.message
                        : "Unable to move menu item",
                ),
            );
            return false;
        }
    }
    const sort = usePointerSort((key, target) => {
        const [parent, before] = JSON.parse(target) as [
            string,
            string | undefined,
        ];
        if (key === before || descendants(nodes, key).has(parent)) return;
        moveItem(key, parent, before);
    });
    const branches = useMemo(
        () =>
            new Map(
                nodes.map((node) => [node.key, descendants(nodes, node.key)]),
            ),
        [nodes],
    );
    const draggedBranch = sort.active ? branches.get(sort.active) : undefined;
    const dropId = (parent: string, before?: string) =>
        JSON.stringify([parent, before]);
    useUnsavedChanges(
        form.isDirty || JSON.stringify(nodes) !== nodeBaseline,
        form.processing,
    );
    function change(key: string, values: Partial<MenuItem>) {
        setNodes((items) =>
            items.map((item) =>
                item.key === key ? { ...item, ...values } : item,
            ),
        );
    }
    function addReferences() {
        if (!selectedReferences.length) return;
        const added = referenceMenuItems(
            selectedReferences,
            () => crypto.randomUUID(),
            iconImageEnabled,
        );
        for (const node of added)
            nodeEditSnapshots.current.set(node.key, { ...node });
        setNodes((current) => [...current, ...added]);
        setSelectedReferences([]);
        setNotice(t("Menu item added"));
    }
    function add() {
        const node: MenuItem = {
            key: crypto.randomUUID(),
            parent: "",
            title: linkTitle.trim(),
            url: linkUrl.trim(),
            reference_type: "",
            reference_id: "",
            icon_font: "",
            ...(iconImageEnabled ? { icon_image: "" } : {}),
            css_class: "",
            target: "_self",
        };
        nodeEditSnapshots.current.set(node.key, { ...node });
        setNodes((current) => [...current, node]);
        setLinkTitle("");
        setLinkUrl("/");
        setNotice(t("Menu item added"));
    }
    function cancelItem(key: string) {
        const snapshot =
            nodeEditSnapshots.current.get(key) ??
            (JSON.parse(nodeBaseline) as MenuItem[]).find(
                (item) => item.key === key,
            );
        if (snapshot)
            setNodes((current) => cancelMenuItem(current, key, snapshot));
        nodeEditSnapshots.current.delete(key);
        form.clearErrors("menu_nodes");
        const summary = nodeSummaries.current.get(key);
        const details = summary?.parentElement;
        if (details instanceof HTMLDetailsElement) details.open = false;
        reparentedFocus.current = null;
        requestAnimationFrame(() => {
            const currentSummary = nodeSummaries.current.get(key);
            const currentDetails = currentSummary?.parentElement;
            if (currentDetails instanceof HTMLDetailsElement)
                currentDetails.open = false;
            currentSummary?.focus();
        });
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        try {
            const retained = new Set(nodes.map((node) => node.id));
            form.transform((data) => ({
                ...data,
                menu_nodes: JSON.stringify(serializeMenu(nodes)),
                deleted_nodes: menu.nodes
                    .filter((node) => node.id && !retained.has(node.id))
                    .map((node) => node.id)
                    .join(" "),
            }));
            form.post(urls.submit, {
                preserveScroll: true,
                onSuccess: (page) => {
                    const fresh = page.props.menu as Props["menu"] | undefined;
                    const nextLanguage = page.props
                        .language as Props["language"];
                    if (fresh) {
                        const reconciled = reconcileMenuSave(
                            nodes,
                            fresh.nodes,
                            latestNodes.current,
                        );
                        for (const node of reconciled.baseline)
                            nodeEditSnapshots.current.delete(node.key);
                        const currentKeys = new Set(
                            reconciled.current.map((node) => node.key),
                        );
                        for (const key of nodeEditSnapshots.current.keys()) {
                            if (!currentKeys.has(key))
                                nodeEditSnapshots.current.delete(key);
                        }
                        setNodes(reconciled.current);
                        setNodeBaseline(JSON.stringify(reconciled.baseline));
                        const next = {
                            ...form.data,
                            name: fresh.name,
                            status: fresh.status,
                            locations: fresh.locations,
                            language: nextLanguage?.code ?? "",
                            ref_from: nextLanguage?.refFrom ?? "",
                        };
                        form.setData(
                            mergeSavedFields(
                                form.data,
                                next,
                                latestForm.current,
                            ),
                        );
                        form.setDefaults(next);
                    }
                },
            });
        } catch {
            form.setError(
                "menu_nodes",
                t("Menu depth cannot exceed five levels."),
            );
        }
    }
    return (
        <AdminLayout
            title={title}
            actions={<LanguageSwitcher languages={language?.choices ?? []} />}
        >
            <Head title={title} />
            {sort.overlay}
            <form onSubmit={submit} className="space-y-5">
                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div className="space-y-5">
                        <Card>
                            <CardContent className="space-y-4">
                                <Field
                                    label={t("Name")}
                                    required
                                    error={form.errors.name}
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            required
                                            minLength={3}
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
                            </CardContent>
                        </Card>
                        {menu.id && (
                            <div className="grid items-start gap-5 xl:grid-cols-[17rem_minmax(0,1fr)]">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            {t("Add menu items")}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {" "}
                                        <SearchInput
                                            className="flex-1"
                                            onClear={() => {
                                                setSearch("");
                                                setReferenceNumber(1);
                                            }}
                                            aria-label={t("Search content")}
                                            placeholder={t("Search content")}
                                            value={search}
                                            onChange={(event) => {
                                                setSearch(event.target.value);
                                                setReferenceNumber(1);
                                            }}
                                        />
                                        <Select
                                            aria-label={t("Content type")}
                                            value={referenceType}
                                            onValueChange={(value) => {
                                                setReferenceType(value);
                                                setReferenceNumber(1);
                                            }}
                                        >
                                            {referenceTypes.map((type) => (
                                                <option
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </option>
                                            ))}
                                        </Select>
                                        {referencesError && (
                                            <div
                                                role="alert"
                                                className="space-y-2 text-sm text-destructive"
                                            >
                                                <p>{referencesError}</p>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setRetryReferences(
                                                            (value) =>
                                                                value + 1,
                                                        )
                                                    }
                                                >
                                                    {t("Retry")}
                                                </Button>
                                            </div>
                                        )}
                                        <div
                                            aria-live="polite"
                                            className="text-xs text-muted-foreground"
                                        >
                                            {referencesLoading ? (
                                                <LoadingIndicator
                                                    label={t("Loading...")}
                                                    announce={false}
                                                />
                                            ) : (
                                                `${referenceResults.total} ${t("items")}`
                                            )}
                                        </div>
                                        <div className="space-y-3">
                                            <Select
                                                multiple
                                                aria-label={t("Select content")}
                                                value={selectedReferences.map(
                                                    (item) => item.value,
                                                )}
                                                onValuesChange={(values) =>
                                                    setSelectedReferences(
                                                        (current) =>
                                                            selectMenuReferences(
                                                                current,
                                                                referenceResults.data,
                                                                values,
                                                            ),
                                                    )
                                                }
                                            >
                                                {visibleReferences.map(
                                                    (item) => (
                                                        <option
                                                            key={item.value}
                                                            value={item.value}
                                                        >
                                                            {item.label}
                                                        </option>
                                                    ),
                                                )}
                                            </Select>
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                disabled={
                                                    !selectedReferences.length ||
                                                    referencesLoading
                                                }
                                                onClick={addReferences}
                                            >
                                                <Plus />
                                                {t("Add selected items")}
                                                {selectedReferences.length >
                                                    0 &&
                                                    ` (${selectedReferences.length})`}
                                            </Button>
                                        </div>
                                        <details className="rounded-md border p-3">
                                            <summary className="cursor-pointer text-sm font-medium">
                                                {t("Custom link")}
                                            </summary>
                                            <div className="mt-3 space-y-3">
                                                <Field
                                                    label={t("Link label")}
                                                    required
                                                >
                                                    {(id) => (
                                                        <Input
                                                            id={id}
                                                            maxLength={120}
                                                            value={linkTitle}
                                                            onChange={(event) =>
                                                                setLinkTitle(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </Field>
                                                <Field
                                                    label={t("URL")}
                                                    required
                                                >
                                                    {(id) => (
                                                        <Input
                                                            id={id}
                                                            maxLength={2048}
                                                            value={linkUrl}
                                                            onChange={(event) =>
                                                                setLinkUrl(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </Field>
                                                <Button
                                                    variant="outline"
                                                    disabled={
                                                        !linkTitle.trim() ||
                                                        !linkUrl.trim()
                                                    }
                                                    onClick={add}
                                                >
                                                    <Plus />
                                                    {t("Add item")}
                                                </Button>
                                            </div>
                                        </details>
                                        <div className="flex items-center justify-between gap-2 border-t pt-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    referencesLoading ||
                                                    referenceNumber <= 1
                                                }
                                                onClick={() =>
                                                    setReferenceNumber(
                                                        (page) => page - 1,
                                                    )
                                                }
                                            >
                                                {t("Previous")}
                                            </Button>
                                            <span className="text-xs text-muted-foreground">
                                                {referenceResults.current_page}{" "}
                                                / {referenceResults.last_page}
                                            </span>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    referencesLoading ||
                                                    referenceNumber >=
                                                        referenceResults.last_page
                                                }
                                                onClick={() =>
                                                    setReferenceNumber(
                                                        (page) => page + 1,
                                                    )
                                                }
                                            >
                                                {t("Next")}
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card
                                    className="min-w-0"
                                    data-sort-target={dropId("")}
                                >
                                    <CardHeader>
                                        <CardTitle>
                                            {t("Menu structure")}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <p className="text-xs leading-relaxed text-muted-foreground">
                                            {t(
                                                "Drag up or down to reorder, or right to nest. Use the arrow buttons and Parent field with a keyboard.",
                                            )}
                                        </p>
                                        {Object.entries(form.errors)
                                            .filter(
                                                ([key]) =>
                                                    key.startsWith(
                                                        "menu_nodes",
                                                    ) ||
                                                    key === "deleted_nodes",
                                            )
                                            .map(([key, message]) => (
                                                <p
                                                    key={key}
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {message}
                                                </p>
                                            ))}
                                        <ol className="space-y-3">
                                            <MenuTree
                                                context={{
                                                    nodes,
                                                    branches,
                                                    sort,
                                                    dropId,
                                                    latestNodes,
                                                    nodeEditSnapshots,
                                                    nodeSummaries,
                                                    draggedBranch,
                                                    setNodes,
                                                    setNotice,
                                                    form,
                                                    change,
                                                    moveItem,
                                                    reparentedFocus,
                                                    iconImageEnabled,
                                                    media,
                                                    cancelItem,
                                                }}
                                            />
                                        </ol>
                                        {sort.active && (
                                            <div
                                                data-sort-target={dropId("")}
                                                className={cn(
                                                    "rounded border border-dashed p-4 text-center text-xs text-muted-foreground",
                                                    sort.target ===
                                                        dropId("") &&
                                                        "border-primary bg-primary/10 text-primary",
                                                )}
                                            >
                                                {t(
                                                    "Drop here to move to top level",
                                                )}
                                            </div>
                                        )}
                                        {!nodes.length && (
                                            <p className="py-6 text-center text-sm text-muted-foreground">
                                                {t("No menu items yet")}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </div>
                    <div className="space-y-5 lg:sticky lg:top-20">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Publish")}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Field
                                    label={t("Status")}
                                    error={form.errors.status}
                                >
                                    {(id) => (
                                        <Select
                                            id={id}
                                            value={form.data.status}
                                            onValueChange={(value) =>
                                                form.setData("status", value)
                                            }
                                        >
                                            {statuses.map((status) => (
                                                <option
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </Field>
                            </CardContent>
                            <div className="flex flex-wrap gap-2 border-t px-5 py-4">
                                <Button type="submit" loading={form.processing}>
                                    {t("Save")}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={urls.index}>{t("Cancel")}</Link>
                                </Button>
                            </div>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("Menu settings")}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {locations.map((location) => (
                                    <label
                                        key={location.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.locations.includes(
                                                location.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    "locations",
                                                    checked === true
                                                        ? [
                                                              ...form.data
                                                                  .locations,
                                                              location.value,
                                                          ]
                                                        : form.data.locations.filter(
                                                              (value) =>
                                                                  value !==
                                                                  location.value,
                                                          ),
                                                )
                                            }
                                        />
                                        {location.label}
                                    </label>
                                ))}
                                {form.errors.locations && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {form.errors.locations}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
                <p role="status" className="sr-only">
                    {notice}
                </p>
            </form>
        </AdminLayout>
    );
}
