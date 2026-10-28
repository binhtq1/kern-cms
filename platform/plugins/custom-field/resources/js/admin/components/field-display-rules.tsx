import { Button } from "@admin/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@admin/components/ui/card";
import { Select } from "@admin/components/ui/select";
import { useAdminTranslation } from "@admin/lib/translate";
import { Trash2 } from "lucide-react";
import { type DisplayRule } from "../FieldGroups/field-group-state";

import type { Dispatch, SetStateAction } from "react";
export function FieldDisplayRules({
    rules,
    schema,
    setRules,
    addRule,
}: {
    rules: DisplayRule[][];
    schema: { slug: string; title: string; data: Record<string, string> }[];
    setRules: Dispatch<SetStateAction<DisplayRule[][]>>;
    addRule: () => { name: string; type: string; value: string };
}) {
    const t = useAdminTranslation();
    return (
        <Card>
            <CardHeader>
                <CardTitle>{t("Display rules")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {rules.map((and, groupIndex) => (
                    <div
                        key={groupIndex}
                        className="space-y-2 rounded-md border p-3"
                    >
                        {and.map((rule, index) => {
                            const choices =
                                schema.find((item) => item.slug === rule.name)
                                    ?.data ?? {};
                            function update(patch: Partial<DisplayRule>) {
                                setRules(
                                    rules.map((group, i) =>
                                        i === groupIndex
                                            ? group.map((item, j) =>
                                                  j === index
                                                      ? {
                                                            ...item,
                                                            ...patch,
                                                        }
                                                      : item,
                                              )
                                            : group,
                                    ),
                                );
                            }
                            return (
                                <div
                                    key={index}
                                    className="flex flex-wrap gap-2"
                                >
                                    <Select
                                        aria-label={t("Rule")}
                                        className="min-w-32 flex-1"
                                        value={rule.name}
                                        onValueChange={(value) =>
                                            update({
                                                name: value,
                                                value:
                                                    Object.keys(
                                                        schema.find(
                                                            (item) =>
                                                                item.slug ===
                                                                value,
                                                        )?.data ?? {},
                                                    )[0] ?? "",
                                            })
                                        }
                                    >
                                        {schema.map((item) => (
                                            <option
                                                key={item.slug}
                                                value={item.slug}
                                            >
                                                {item.title}
                                            </option>
                                        ))}
                                    </Select>
                                    <Select
                                        aria-label={t("Operator")}
                                        className="w-20"
                                        value={rule.type}
                                        onValueChange={(value) =>
                                            update({
                                                type: value,
                                            })
                                        }
                                    >
                                        <option value="==">=</option>
                                        <option value="!=">≠</option>
                                    </Select>
                                    <Select
                                        aria-label={t("Value")}
                                        className="min-w-32 flex-1"
                                        value={rule.value}
                                        onValueChange={(value) =>
                                            update({
                                                value: value,
                                            })
                                        }
                                    >
                                        {Object.entries(choices).map(
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
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t("Remove")}
                                        onClick={() =>
                                            setRules(
                                                rules
                                                    .map((group, i) =>
                                                        i === groupIndex
                                                            ? group.filter(
                                                                  (_, j) =>
                                                                      j !==
                                                                      index,
                                                              )
                                                            : group,
                                                    )
                                                    .filter(
                                                        (group) => group.length,
                                                    ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            );
                        })}
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                setRules(
                                    rules.map((group, i) =>
                                        i === groupIndex
                                            ? [...group, addRule()]
                                            : group,
                                    ),
                                )
                            }
                        >
                            {t("Add AND rule")}
                        </Button>
                    </div>
                ))}
                <Button
                    variant="outline"
                    onClick={() => setRules([...rules, [addRule()]])}
                >
                    {t("Add OR group")}
                </Button>
            </CardContent>
        </Card>
    );
}
