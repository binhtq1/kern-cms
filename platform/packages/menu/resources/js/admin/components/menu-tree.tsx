import { Field } from "@admin/components/field";
import { MediaPicker } from "@admin/components/media-picker";
import { Badge } from "@admin/components/ui/badge";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import { cn } from "@admin/lib/utils";
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    GripVertical,
    Trash2,
} from "lucide-react";
import { reorderMenu, type MenuItem } from "../lib/tree";

import type { InertiaFormProps } from "@inertiajs/react";
import type { Dispatch, RefObject, SetStateAction } from "react";
type MenuTreeContext = {
    nodes: MenuItem[];
    branches: Map<string, Set<string>>;
    sort: ReturnType<typeof usePointerSort>;
    dropId: (parent: string, before?: string | undefined) => string;
    latestNodes: RefObject<MenuItem[]>;
    nodeEditSnapshots: RefObject<Map<string, MenuItem>>;
    nodeSummaries: RefObject<Map<string, HTMLElement>>;
    draggedBranch: Set<string> | undefined;
    setNodes: Dispatch<SetStateAction<MenuItem[]>>;
    setNotice: Dispatch<SetStateAction<string>>;
    form: InertiaFormProps<{
        name: string;
        status: string;
        locations: string[];
        menu_nodes: string;
        deleted_nodes: string;
        language: string;
        ref_from: string;
    }>;
    change: (key: string, values: Partial<MenuItem>) => void;
    moveItem: (
        key: string,
        parent: string,
        before?: string | undefined,
    ) => boolean;
    reparentedFocus: RefObject<string | null>;
    iconImageEnabled: boolean;
    media: { list: string; upload: string } | null;
    cancelItem: (key: string) => void;
};
export function MenuTree({
    context,
    parent = "",
    depth = 0,
}: {
    context: MenuTreeContext;
    parent?: string;
    depth?: number;
}) {
    const {
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
    } = context;
    const t = useAdminTranslation();

    return nodes
        .filter((item) => item.parent === parent)
        .map((node) => {
            const blocked = branches.get(node.key)!;
            const siblings = nodes.filter((item) => item.parent === parent);
            const index = siblings.findIndex((item) => item.key === node.key);
            return (
                <li key={node.key} className="space-y-3">
                    <Card
                        className={cn(
                            "relative overflow-hidden",
                            sort.active === node.key && "opacity-40",
                            sort.target === dropId(node.parent, node.key) &&
                                "before:absolute before:inset-x-0 before:top-0 before:z-10 before:h-1 before:bg-primary",
                            sort.target ===
                                dropId(node.parent, siblings[index + 1]?.key) &&
                                "after:absolute after:inset-x-0 after:bottom-0 after:z-10 after:h-1 after:bg-primary",
                            sort.target === dropId(node.key) &&
                                "border-primary ring-2 ring-primary/20",
                        )}
                    >
                        <details
                            className="group/node"
                            open={!node.id}
                            onToggle={(event) => {
                                if (
                                    event.target !== event.currentTarget ||
                                    !event.currentTarget.open
                                )
                                    return;
                                const current = latestNodes.current.find(
                                    (item) => item.key === node.key,
                                );
                                if (
                                    current &&
                                    !nodeEditSnapshots.current.has(node.key)
                                )
                                    nodeEditSnapshots.current.set(node.key, {
                                        ...current,
                                    });
                            }}
                        >
                            <summary
                                ref={(element) => {
                                    if (element)
                                        nodeSummaries.current.set(
                                            node.key,
                                            element,
                                        );
                                    else nodeSummaries.current.delete(node.key);
                                }}
                                data-sort-before={
                                    !draggedBranch?.has(node.key)
                                        ? dropId(node.parent, node.key)
                                        : undefined
                                }
                                data-sort-after={
                                    !draggedBranch?.has(node.key)
                                        ? dropId(
                                              node.parent,
                                              siblings[index + 1]?.key,
                                          )
                                        : undefined
                                }
                                data-sort-inside={
                                    !draggedBranch?.has(node.key)
                                        ? dropId(node.key)
                                        : undefined
                                }
                                className="flex cursor-pointer list-none items-center gap-2 bg-muted/20 px-3 py-2 [&::-webkit-details-marker]:hidden"
                            >
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    {...sort.handle(
                                        node.key,
                                        node.title || t("New item"),
                                    )}
                                    title={t("Drag to reorder")}
                                    aria-label={`${t("Drag to reorder")}: ${node.title}`}
                                    className="shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
                                >
                                    <GripVertical className="size-4" />
                                </Button>
                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                    {node.title || t("New item")}
                                </span>
                                {sort.target === dropId(node.key) && (
                                    <Badge variant="outline">
                                        {t("Nest here")}
                                    </Badge>
                                )}
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    disabled={index === 0}
                                    aria-label={t("Move up")}
                                    onClick={() => {
                                        setNodes(
                                            reorderMenu(nodes, node.key, -1),
                                        );
                                        setNotice(t("Item moved"));
                                    }}
                                >
                                    <ArrowUp />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    disabled={index === siblings.length - 1}
                                    aria-label={t("Move down")}
                                    onClick={() => {
                                        setNodes(
                                            reorderMenu(nodes, node.key, 1),
                                        );
                                        setNotice(t("Item moved"));
                                    }}
                                >
                                    <ArrowDown />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={t("Remove item")}
                                    onClick={() => {
                                        setNodes(
                                            nodes
                                                .filter(
                                                    (item) =>
                                                        item.key !== node.key,
                                                )
                                                .map((item) =>
                                                    item.parent === node.key
                                                        ? {
                                                              ...item,
                                                              parent: node.parent,
                                                          }
                                                        : item,
                                                ),
                                        );
                                        setNotice(t("Item removed"));
                                    }}
                                >
                                    <Trash2 />
                                </Button>
                                <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-open/node:rotate-180" />
                            </summary>
                            <CardContent className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                                <Field
                                    label={t("Title")}
                                    error={
                                        form.errors[
                                            `menu_nodes.${node.key}` as keyof typeof form.errors
                                        ]
                                    }
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            maxLength={120}
                                            value={node.title}
                                            onChange={(event) =>
                                                change(node.key, {
                                                    title: event.target.value,
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label={t("URL")}
                                    description={
                                        node.reference_type
                                            ? t(
                                                  "Linked content URL updates automatically",
                                              )
                                            : undefined
                                    }
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            disabled={Boolean(
                                                node.reference_type,
                                            )}
                                            value={node.url}
                                            onChange={(event) =>
                                                change(node.key, {
                                                    url: event.target.value,
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field label={t("Parent item")}>
                                    {(id) => (
                                        <Select
                                            id={id}
                                            value={node.parent}
                                            onValueChange={(value) => {
                                                if (moveItem(node.key, value))
                                                    reparentedFocus.current =
                                                        node.key;
                                            }}
                                        >
                                            <option value="">
                                                {t("Top level")}
                                            </option>
                                            {nodes
                                                .filter(
                                                    (item) =>
                                                        !blocked.has(item.key),
                                                )
                                                .map((item) => (
                                                    <option
                                                        key={item.key}
                                                        value={item.key}
                                                    >
                                                        {item.title ||
                                                            t("New item")}
                                                    </option>
                                                ))}
                                        </Select>
                                    )}
                                </Field>
                                <Field label={t("Open link")}>
                                    {(id) => (
                                        <Select
                                            id={id}
                                            value={node.target}
                                            onValueChange={(value) =>
                                                change(node.key, {
                                                    target: value,
                                                })
                                            }
                                        >
                                            <option value="_self">
                                                {t("Same tab")}
                                            </option>
                                            <option value="_blank">
                                                {t("New tab")}
                                            </option>
                                        </Select>
                                    )}
                                </Field>
                                <Field label={t("CSS class")}>
                                    {(id) => (
                                        <Input
                                            id={id}
                                            maxLength={120}
                                            value={node.css_class}
                                            onChange={(event) =>
                                                change(node.key, {
                                                    css_class:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field label={t("Icon")}>
                                    {(id) => (
                                        <Input
                                            id={id}
                                            maxLength={50}
                                            value={node.icon_font}
                                            onChange={(event) =>
                                                change(node.key, {
                                                    icon_font:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                {iconImageEnabled && (
                                    <Field
                                        label={t("Icon image")}
                                        description={t(
                                            "An image replaces the font icon.",
                                        )}
                                    >
                                        {(id) => (
                                            <MediaPicker
                                                id={id}
                                                aspect="square"
                                                value={node.icon_image ?? ""}
                                                onChange={(value) =>
                                                    change(node.key, {
                                                        icon_image: value,
                                                    })
                                                }
                                                listUrl={media?.list}
                                                uploadUrl={media?.upload}
                                            />
                                        )}
                                    </Field>
                                )}
                                <div className="flex justify-end sm:col-span-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => cancelItem(node.key)}
                                    >
                                        {t("Cancel")}
                                    </Button>
                                </div>
                            </CardContent>
                        </details>
                    </Card>
                    {depth < 6 &&
                        nodes.some((item) => item.parent === node.key) && (
                            <ol className="space-y-3 border-l pl-3 sm:pl-6">
                                <MenuTree
                                    context={context}
                                    parent={node.key}
                                    depth={depth + 1}
                                />
                            </ol>
                        )}
                </li>
            );
        });
}
