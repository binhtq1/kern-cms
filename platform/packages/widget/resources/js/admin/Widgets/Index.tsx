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
import { Select } from "@admin/components/ui/select";
import AdminLayout from "@admin/layouts/admin-layout";
import { moveGroupedItem } from "@admin/lib/sort";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import { useUnsavedChanges } from "@admin/lib/use-unsaved-changes";
import { cn } from "@admin/lib/utils";
import { createUuid } from "@admin/lib/uuid";
import { Head } from "@inertiajs/react";
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    GripVertical,
    Plus,
    Trash2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { WidgetFieldControl } from "../components/widget-field-control";
import { useWidgetGroups } from "../hooks/use-widget-groups";
import type { Definition, Group, Item, Value } from "../lib/widget-types";
export default function Widgets({
    title,
    registry,
    groups: initialGroups,
    urls,
    languages = [],
}: {
    title: string;
    registry: Definition[];
    groups: Group[];
    urls: { save: string };
    languages?: LanguageChoice[];
}) {
    const t = useAdminTranslation();
    const {
        groups,
        groupsRef,
        committed,
        saving,
        saveError,
        setSaveError,
        dirty,
        queue,
        applyGroups,
    } = useWidgetGroups(initialGroups, urls.save);
    const [active, setActive] = useState(initialGroups[0]?.id ?? "");
    const widgetSummaries = useRef(new Map<string, HTMLElement>());
    const movedWidgetFocus = useRef<string | null>(null);
    useEffect(() => {
        const key = movedWidgetFocus.current;
        if (!key) return;
        const frame = requestAnimationFrame(() => {
            const summary = widgetSummaries.current.get(key);
            if (!summary?.isConnected) return;
            const details = summary.parentElement;
            if (details instanceof HTMLDetailsElement) details.open = true;
            summary.focus();
            movedWidgetFocus.current = null;
        });
        return () => cancelAnimationFrame(frame);
    }, [groups, active]);
    const [search, setSearch] = useState("");
    const [notice, setNotice] = useState("");
    const targetId = (area: string, before?: string) =>
        JSON.stringify([area, before]);
    const sort = usePointerSort((key, target) => {
        const [area, before] = JSON.parse(target) as [
            string,
            string | undefined,
        ];
        const definition = key.startsWith("new:")
            ? registry.find((entry) => entry.id === key.slice(4))
            : null;
        const item = definition
            ? {
                  key: createUuid(),
                  id: definition.id,
                  data: structuredClone(definition.defaults),
              }
            : groups
                  .flatMap((entry) => entry.items)
                  .find((entry) => entry.key === key);
        const next = moveGroupedItem(
            groups,
            key,
            area,
            before,
            definition ? item : undefined,
        );
        if (next === groups) return;
        applyGroups(next, true);
        setActive(area);
        setNotice(t(definition ? "Widget added" : "Item moved"));
    });
    useEffect(() => {
        if (!sort.active || !sort.target) return;
        const area = (JSON.parse(sort.target) as [string])[0];
        const timer = setTimeout(() => setActive(area), 400);
        return () => clearTimeout(timer);
    }, [sort.active, sort.target]);
    useUnsavedChanges(dirty || saving, saving);
    const group = groups.find((item) => item.id === active);
    const filteredWidgets = registry.filter((widget) =>
        widget.name.toLowerCase().includes(search.toLowerCase()),
    );
    function update(items: Item[], message?: string) {
        applyGroups(
            groupsRef.current.map((item) =>
                item.id === active ? { ...item, items } : item,
            ),
            Boolean(message),
        );
        if (message) setNotice(message);
    }
    function change(key: string, name: string, value: Value) {
        if (group)
            update(
                group.items.map((item) =>
                    item.key === key
                        ? { ...item, data: { ...item.data, [name]: value } }
                        : item,
                ),
            );
    }
    function reorder(key: string, direction: -1 | 1) {
        if (!group) return;
        const index = group.items.findIndex((item) => item.key === key);
        const next = index + direction;
        if (next < 0 || next >= group.items.length) return;
        const items = [...group.items];
        [items[index], items[next]] = [items[next], items[index]];
        update(items, t("Item moved"));
    }
    function move(key: string, target: string) {
        const item = group?.items.find((entry) => entry.key === key);
        if (!item || target === active) return;
        movedWidgetFocus.current = key;
        applyGroups(
            groupsRef.current.map((entry) =>
                entry.id === active
                    ? {
                          ...entry,
                          items: entry.items.filter(
                              (widget) => widget.key !== key,
                          ),
                      }
                    : entry.id === target
                      ? { ...entry, items: [...entry.items, item] }
                      : entry,
            ),
            true,
        );
        setActive(target);
        setNotice(t("Item moved"));
    }
    function save() {
        for (const item of groupsRef.current.flatMap((area) => area.items))
            committed.current.set(item.key, structuredClone(item.data));
        setSaveError("");
        queue.enqueue(groupsRef.current);
    }
    function saveWidget(key: string) {
        const item = groupsRef.current
            .flatMap((area) => area.items)
            .find((entry) => entry.key === key);
        if (!item) return;
        committed.current.set(key, structuredClone(item.data));
        setSaveError("");
        applyGroups(groupsRef.current, true);
    }
    return (
        <AdminLayout
            title={title}
            actions={
                <Button loading={saving} disabled={!dirty} onClick={save}>
                    {t("Save changes")}
                </Button>
            }
        >
            <Head title={title} />
            {sort.overlay}
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    {t(
                        "Drag widgets into an area or use +. Reorder with the handle or arrow buttons.",
                    )}
                </p>
                <LanguageSwitcher languages={languages} />
            </div>
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(240px,0.7fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle className="min-w-0 truncate">
                            {t("Available widgets")}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <SearchInput
                            onClear={() => {
                                setSearch("");
                            }}
                            className="sm:col-span-2 lg:col-span-1 xl:col-span-2"
                            aria-label={t("Search widgets")}
                            placeholder={t("Search widgets")}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        {filteredWidgets.length === 0 && (
                            <div className="space-y-3 py-6 text-center sm:col-span-2 lg:col-span-1 xl:col-span-2">
                                <p
                                    role="status"
                                    className="text-sm text-muted-foreground"
                                >
                                    {t("No results found")}
                                </p>
                                {search && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setSearch("")}
                                    >
                                        {t("Clear search")}
                                    </Button>
                                )}
                            </div>
                        )}
                        {filteredWidgets.map((widget) => (
                            <div
                                key={widget.id}
                                className="space-y-2 rounded-md border p-3"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        {...sort.handle(
                                            `new:${widget.id}`,
                                            widget.name,
                                        )}
                                        aria-label={`${t("Drag to reorder")}: ${widget.name}`}
                                        className="shrink-0 cursor-grab text-muted-foreground"
                                    >
                                        <GripVertical className="size-4" />
                                    </Button>
                                    <h3 className="min-w-0 flex-1 text-sm font-medium">
                                        {widget.name}
                                    </h3>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        disabled={!group}
                                        aria-label={`${t("Add")} ${widget.name}`}
                                        onClick={() =>
                                            group &&
                                            update(
                                                [
                                                    ...group.items,
                                                    {
                                                        key: createUuid(),
                                                        id: widget.id,
                                                        data: structuredClone(
                                                            widget.defaults,
                                                        ),
                                                    },
                                                ],
                                                t("Widget added"),
                                            )
                                        }
                                    >
                                        <Plus />
                                    </Button>
                                </div>
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    {widget.description}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <div className="space-y-4">
                    {saving ? (
                        <LoadingIndicator label={t("Saving widgets…")} />
                    ) : (
                        dirty && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {t("You have unsaved changes")}
                            </p>
                        )
                    )}
                    {saveError && (
                        <div
                            className="flex flex-wrap items-center gap-3"
                            role="alert"
                        >
                            <p className="text-sm text-destructive">
                                {saveError}
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={saving}
                                onClick={() => {
                                    setSaveError("");
                                    queue.retry();
                                }}
                            >
                                {t("Retry")}
                            </Button>
                        </div>
                    )}
                    {groups.map((area) => (
                        <Card
                            key={area.id}
                            data-sort-target={targetId(area.id)}
                            className={cn(
                                "overflow-hidden",
                                sort.target === targetId(area.id) &&
                                    "border-primary ring-2 ring-primary/20",
                            )}
                        >
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-3 bg-muted/20 px-5 py-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                                onClick={() =>
                                    setActive(active === area.id ? "" : area.id)
                                }
                                aria-expanded={active === area.id}
                            >
                                <div>
                                    <h2 className="text-sm font-semibold">
                                        {area.name}{" "}
                                        <span className="ml-2 text-xs font-normal tabular-nums text-muted-foreground">
                                            ({area.items.length})
                                        </span>
                                    </h2>
                                    {area.description && (
                                        <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                            {area.description}
                                        </p>
                                    )}
                                </div>
                                <ChevronDown
                                    className={
                                        active === area.id
                                            ? "size-4 rotate-180 text-muted-foreground"
                                            : "size-4 text-muted-foreground"
                                    }
                                />
                            </button>
                            {active === area.id && (
                                <div className="space-y-3 border-t p-3">
                                    <ol className="space-y-4">
                                        {group?.items.map((item, index) => {
                                            const definition = registry.find(
                                                (entry) => entry.id === item.id,
                                            );
                                            return (
                                                <li
                                                    key={item.key}
                                                    className={cn(
                                                        "relative",
                                                        sort.active ===
                                                            item.key &&
                                                            "opacity-40",
                                                        sort.target ===
                                                            targetId(
                                                                area.id,
                                                                item.key,
                                                            ) &&
                                                            "before:pointer-events-none before:absolute before:inset-x-0 before:-top-2 before:z-10 before:h-1 before:rounded before:bg-primary",
                                                        sort.target ===
                                                            targetId(
                                                                area.id,
                                                                area.items[
                                                                    index + 1
                                                                ]?.key,
                                                            ) &&
                                                            "after:pointer-events-none after:absolute after:inset-x-0 after:-bottom-2 after:z-10 after:h-1 after:rounded after:bg-primary",
                                                    )}
                                                >
                                                    <Card className="overflow-hidden">
                                                        <details className="group/widget">
                                                            <summary
                                                                ref={(
                                                                    element,
                                                                ) => {
                                                                    if (element)
                                                                        widgetSummaries.current.set(
                                                                            item.key,
                                                                            element,
                                                                        );
                                                                    else
                                                                        widgetSummaries.current.delete(
                                                                            item.key,
                                                                        );
                                                                }}
                                                                data-sort-before={targetId(
                                                                    area.id,
                                                                    item.key,
                                                                )}
                                                                data-sort-after={targetId(
                                                                    area.id,
                                                                    area.items[
                                                                        index +
                                                                            1
                                                                    ]?.key,
                                                                )}
                                                                className="cursor-pointer list-none [&::-webkit-details-marker]:hidden"
                                                            >
                                                                <CardHeader className="flex items-center justify-between gap-2 space-y-0 border-b-0 px-3 py-2">
                                                                    <div className="flex min-w-0 flex-1 items-center gap-2">
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            {...sort.handle(
                                                                                item.key,
                                                                                String(
                                                                                    item
                                                                                        .data
                                                                                        .name ||
                                                                                        definition?.name ||
                                                                                        item.id,
                                                                                ),
                                                                            )}
                                                                            aria-label={`${t("Drag to reorder")}: ${definition?.name || item.id}`}
                                                                            className="shrink-0 cursor-grab text-muted-foreground"
                                                                        >
                                                                            <GripVertical className="size-4" />
                                                                        </Button>
                                                                        <CardTitle className="min-w-0 truncate">
                                                                            {String(
                                                                                item
                                                                                    .data
                                                                                    .name ||
                                                                                    definition?.name ||
                                                                                    item.id,
                                                                            )}
                                                                        </CardTitle>
                                                                    </div>
                                                                    <div
                                                                        className="flex shrink-0 items-center gap-0.5"
                                                                        onClick={(
                                                                            event,
                                                                        ) =>
                                                                            event.stopPropagation()
                                                                        }
                                                                    >
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            disabled={
                                                                                index ===
                                                                                0
                                                                            }
                                                                            aria-label={t(
                                                                                "Move up",
                                                                            )}
                                                                            onClick={() =>
                                                                                reorder(
                                                                                    item.key,
                                                                                    -1,
                                                                                )
                                                                            }
                                                                        >
                                                                            <ArrowUp />
                                                                        </Button>
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            disabled={
                                                                                index ===
                                                                                group
                                                                                    .items
                                                                                    .length -
                                                                                    1
                                                                            }
                                                                            aria-label={t(
                                                                                "Move down",
                                                                            )}
                                                                            onClick={() =>
                                                                                reorder(
                                                                                    item.key,
                                                                                    1,
                                                                                )
                                                                            }
                                                                        >
                                                                            <ArrowDown />
                                                                        </Button>
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            aria-label={t(
                                                                                "Remove widget",
                                                                            )}
                                                                            onClick={() =>
                                                                                update(
                                                                                    group.items.filter(
                                                                                        (
                                                                                            entry,
                                                                                        ) =>
                                                                                            entry.key !==
                                                                                            item.key,
                                                                                    ),
                                                                                    t(
                                                                                        "Widget removed",
                                                                                    ),
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2 />
                                                                        </Button>
                                                                    </div>
                                                                    <ChevronDown className="size-4 shrink-0 text-muted-foreground group-open/widget:rotate-180" />
                                                                </CardHeader>
                                                            </summary>
                                                            <CardContent className="space-y-4 border-t pt-4">
                                                                {definition?.fields.map(
                                                                    (field) => (
                                                                        <WidgetFieldControl
                                                                            key={
                                                                                field.name
                                                                            }
                                                                            field={
                                                                                field
                                                                            }
                                                                            item={
                                                                                item
                                                                            }
                                                                            definition={
                                                                                definition
                                                                            }
                                                                            change={
                                                                                change
                                                                            }
                                                                        />
                                                                    ),
                                                                )}
                                                                {groups.length >
                                                                    1 && (
                                                                    <Field
                                                                        label={t(
                                                                            "Move to area",
                                                                        )}
                                                                    >
                                                                        {(
                                                                            id,
                                                                        ) => (
                                                                            <Select
                                                                                id={
                                                                                    id
                                                                                }
                                                                                value={
                                                                                    active
                                                                                }
                                                                                onValueChange={(
                                                                                    value,
                                                                                ) =>
                                                                                    move(
                                                                                        item.key,
                                                                                        value,
                                                                                    )
                                                                                }
                                                                            >
                                                                                {groups.map(
                                                                                    (
                                                                                        entry,
                                                                                    ) => (
                                                                                        <option
                                                                                            key={
                                                                                                entry.id
                                                                                            }
                                                                                            value={
                                                                                                entry.id
                                                                                            }
                                                                                        >
                                                                                            {
                                                                                                entry.name
                                                                                            }
                                                                                        </option>
                                                                                    ),
                                                                                )}
                                                                            </Select>
                                                                        )}
                                                                    </Field>
                                                                )}
                                                                <Button
                                                                    size="sm"
                                                                    loading={
                                                                        saving
                                                                    }
                                                                    onClick={() =>
                                                                        saveWidget(
                                                                            item.key,
                                                                        )
                                                                    }
                                                                >
                                                                    {t(
                                                                        "Save widget",
                                                                    )}
                                                                </Button>
                                                            </CardContent>
                                                        </details>
                                                    </Card>
                                                </li>
                                            );
                                        })}
                                    </ol>
                                    {!group?.items.length && (
                                        <Card>
                                            <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                                {t("Add a widget to this area")}
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>
                            )}
                        </Card>
                    ))}
                </div>
            </div>
            <p role="status" className="sr-only">
                {notice}
            </p>
        </AdminLayout>
    );
}
