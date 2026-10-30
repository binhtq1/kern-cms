import { Button } from "@admin/components/ui/button";
import { Card, CardHeader } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";
import { usePointerSort } from "@admin/lib/use-pointer-sort";
import { cn } from "@admin/lib/utils";
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronRight,
    GripVertical,
    Plus,
    Trash2,
} from "lucide-react";
import { useId, useRef, useState } from "react";
import {
    fieldItemIdentity,
    moveFieldItem,
    updateFieldItem,
    type FieldItem as Item,
} from "../FieldGroups/field-group-state";

import { newItem, typeLabels } from "../lib/field-group-controls";
import { FieldItemFields } from "./field-item-fields";
export function Builder({
    items,
    onChange,
    types,
    onRemove,
    depth = 0,
}: {
    items: Item[];
    onChange: (items: Item[]) => void;
    types: string[];
    onRemove: (item: Item) => void;
    depth?: number;
}) {
    const t = useAdminTranslation();
    const scope = useId();
    const [collapsed, setCollapsed] = useState<string[]>(() =>
        items.map(fieldItemIdentity),
    );
    const invalidFocusPending = useRef(false);
    const target = (key: string, after = false) =>
        JSON.stringify([scope, key, after]);
    const sort = usePointerSort((source, destination) => {
        const [owner, key, after] = JSON.parse(destination) as [
            string,
            string,
            boolean,
        ];
        if (owner !== scope) return;
        onChange(moveFieldItem(items, source, key, after));
    });
    function change(index: number, patch: Partial<Item>) {
        onChange(
            items.map((item, i) =>
                i === index ? updateFieldItem(item, patch) : item,
            ),
        );
    }
    function move(index: number, offset: number) {
        const adjacent = items[index + offset];
        if (!adjacent) return;
        onChange(
            moveFieldItem(
                items,
                fieldItemIdentity(items[index]),
                fieldItemIdentity(adjacent),
                offset > 0,
            ),
        );
    }
    return (
        <div className="space-y-4">
            {items.map((item, index) => {
                const key = fieldItemIdentity(item);
                const isCollapsed = collapsed.includes(key);
                const before = target(key);
                const after = target(key, true);
                return (
                    <Card
                        key={key}
                        onInvalidCapture={(event) => {
                            if (!isCollapsed) return;
                            event.preventDefault();
                            setCollapsed((current) =>
                                current.filter((value) => value !== key),
                            );
                            if (
                                !invalidFocusPending.current &&
                                event.target instanceof HTMLElement
                            ) {
                                invalidFocusPending.current = true;
                                const control = event.target;
                                requestAnimationFrame(() => {
                                    control.focus();
                                    invalidFocusPending.current = false;
                                });
                            }
                        }}
                        className={cn(sort.active === key && "opacity-50")}
                    >
                        <CardHeader
                            data-sort-before={before}
                            data-sort-after={after}
                            className={cn(
                                "relative flex flex-row flex-wrap items-center justify-between gap-2",
                                sort.target === before &&
                                    "before:absolute before:inset-x-0 before:top-0 before:h-1 before:bg-primary",
                                sort.target === after &&
                                    "after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:bg-primary",
                            )}
                        >
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`${t("Drag to reorder")}: ${item.title || t("New field")}`}
                                {...sort.handle(
                                    key,
                                    item.title || t("New field"),
                                )}
                            >
                                <GripVertical />
                            </Button>
                            <Button
                                variant="ghost"
                                className="h-auto min-w-0 flex-1 justify-start whitespace-normal px-2 py-1 text-left"
                                aria-expanded={!isCollapsed}
                                aria-controls={`${scope}-${key}`}
                                onClick={() =>
                                    setCollapsed((current) =>
                                        isCollapsed
                                            ? current.filter(
                                                  (value) => value !== key,
                                              )
                                            : [...current, key],
                                    )
                                }
                            >
                                {isCollapsed ? (
                                    <ChevronRight />
                                ) : (
                                    <ChevronDown />
                                )}
                                <span className="min-w-0">
                                    <span className="block break-words font-semibold">
                                        {item.title || t("New field")}
                                    </span>
                                    <span className="block break-all text-xs font-normal text-muted-foreground">
                                        {item.slug ? `${item.slug} · ` : ""}
                                        {t(typeLabels[item.type] ?? item.type)}
                                    </span>
                                </span>
                            </Button>
                            <div className="flex gap-1">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={t("Move up")}
                                    disabled={!index}
                                    onClick={() => move(index, -1)}
                                >
                                    <ArrowUp />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={t("Move down")}
                                    disabled={index === items.length - 1}
                                    onClick={() => move(index, 1)}
                                >
                                    <ArrowDown />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={t("Remove")}
                                    onClick={() => {
                                        onRemove(item);
                                        onChange(
                                            items.filter((_, i) => i !== index),
                                        );
                                    }}
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </CardHeader>
                        <FieldItemFields
                            scope={scope}
                            isCollapsed={isCollapsed}
                            item={item}
                            change={change}
                            index={index}
                            onRemove={onRemove}
                            types={types}
                            fieldKey={key}
                        >
                            {item.type === "repeater" && depth < 10 && (
                                <div className="border-l-2 pl-4">
                                    <Builder
                                        items={item.items}
                                        types={types}
                                        onRemove={onRemove}
                                        depth={depth + 1}
                                        onChange={(nested) =>
                                            change(index, { items: nested })
                                        }
                                    />
                                </div>
                            )}
                        </FieldItemFields>
                    </Card>
                );
            })}
            {sort.overlay}
            <Button
                variant="outline"
                onClick={() => onChange([...items, newItem()])}
            >
                <Plus />
                {t("Add field")}
            </Button>
        </div>
    );
}
