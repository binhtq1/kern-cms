import { useMemo, useState } from "react";
import { useAdminTranslation } from "@admin/lib/translate";
import { cn, normalizeSearch } from "@admin/lib/utils";
import type { Option } from "@admin/types";
import { SearchInput } from "./search-input";
import { Checkbox } from "./ui/checkbox";

type CategoryTreeProps = {
    choices: Option[];
    value: string[];
    disabled?: boolean;
    onValueChange: (value: string[]) => void;
};

type CategoryBranchProps = Required<CategoryTreeProps> & {
    childrenByParent: Map<string, Option[]>;
    visible: Set<string> | null;
    ancestors?: string[];
};

function CategoryBranch({
    choices,
    childrenByParent,
    value,
    disabled,
    onValueChange,
    visible,
    ancestors = [],
}: CategoryBranchProps) {
    const items = choices.filter(
        (choice) =>
            !ancestors.includes(choice.value) &&
            (!visible || visible.has(choice.value)),
    );
    if (!items.length) return null;

    return (
        <ul className={cn(ancestors.length > 0 && "ms-4")}>
            {items.map((choice) => (
                <li key={choice.value}>
                    <label
                        className={cn(
                            "flex min-h-11 cursor-pointer items-start gap-1.5 rounded px-1 text-sm leading-5 transition-colors sm:min-h-8",
                            disabled
                                ? "cursor-not-allowed opacity-60"
                                : "hover:bg-muted/60 focus-within:bg-muted/60",
                        )}
                    >
                        <Checkbox
                            className="sm:translate-y-0.5"
                            checked={value.includes(choice.value)}
                            disabled={disabled}
                            onCheckedChange={(checked) =>
                                onValueChange(
                                    checked === true
                                        ? [...new Set([...value, choice.value])]
                                        : value.filter(
                                              (item) => item !== choice.value,
                                          ),
                                )
                            }
                        />
                        <span className="min-w-0 flex-1 py-3 [overflow-wrap:anywhere] sm:py-1.5">
                            {choice.label}
                        </span>
                    </label>
                    <CategoryBranch
                        choices={childrenByParent.get(choice.value) ?? []}
                        childrenByParent={childrenByParent}
                        value={value}
                        disabled={disabled}
                        onValueChange={onValueChange}
                        visible={visible}
                        ancestors={[...ancestors, choice.value]}
                    />
                </li>
            ))}
        </ul>
    );
}

export function CategoryTree({
    choices,
    value,
    disabled = false,
    onValueChange,
}: CategoryTreeProps) {
    const t = useAdminTranslation();
    const [search, setSearch] = useState("");
    const choiceByValue = useMemo(
        () => new Map(choices.map((choice) => [choice.value, choice])),
        [choices],
    );
    const childrenByParent = useMemo(() => {
        const groups = new Map<string, Option[]>();

        choices.forEach((choice) => {
            const parentId =
                choice.parent_id &&
                choice.parent_id !== "0" &&
                choice.parent_id !== choice.value &&
                choiceByValue.has(choice.parent_id)
                    ? choice.parent_id
                    : "";
            const children = groups.get(parentId) ?? [];
            children.push(choice);
            groups.set(parentId, children);
        });

        return groups;
    }, [choiceByValue, choices]);
    const showSearch = choices.length > 10;
    const query = showSearch ? normalizeSearch(search) : "";
    const visible = useMemo(() => {
        if (!query) return null;
        const matches = new Set<string>();

        choices.forEach((choice) => {
            if (!normalizeSearch(choice.label).includes(query)) return;
            const visited = new Set<string>();
            let current: Option | undefined = choice;

            while (current && !visited.has(current.value)) {
                visited.add(current.value);
                matches.add(current.value);
                current = current.parent_id
                    ? choiceByValue.get(current.parent_id)
                    : undefined;
            }
        });

        return matches;
    }, [choices, choiceByValue, query]);

    return (
        <div className="space-y-2">
            {showSearch && (
                <SearchInput
                    value={search}
                    aria-label={t("Search categories")}
                    placeholder={t("Search categories")}
                    disabled={disabled}
                    onChange={(event) => setSearch(event.target.value)}
                    onClear={() => setSearch("")}
                />
            )}
            <div className="max-h-60 overflow-y-auto overscroll-contain rounded-md border bg-card p-1.5 [scrollbar-gutter:stable]">
                {!choices.length || visible?.size === 0 ? (
                    <p
                        role="status"
                        className="px-2 py-3 text-sm text-muted-foreground"
                    >
                        {t(
                            choices.length
                                ? "No results found"
                                : "No categories available",
                        )}
                    </p>
                ) : (
                    <CategoryBranch
                        choices={childrenByParent.get("") ?? []}
                        childrenByParent={childrenByParent}
                        value={value}
                        disabled={disabled}
                        onValueChange={onValueChange}
                        visible={visible}
                    />
                )}
            </div>
        </div>
    );
}
