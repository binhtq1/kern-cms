import { SearchInput } from "@admin/components/search-input";
import { useState } from "react";
import { Type } from "lucide-react";
import type { Option } from "@admin/types";
import { normalizeSearch } from "@admin/lib/utils";
import { useAdminTranslation } from "@admin/lib/translate";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { Dialog } from "./ui/dialog";
export function FontPicker({
    id,
    value,
    onChange,
    choices = [],
    disabled,
    readOnly,
    placeholder,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    choices?: Option[];
    disabled?: boolean;
    readOnly?: boolean;
    placeholder?: string;
}) {
    const t = useAdminTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
    const filtered = choices.filter((choice) =>
        normalizeSearch(choice.label).includes(normalizeSearch(search)),
    );
    return (
        <div className="min-w-0">
            <div className="flex gap-2">
                <Input
                    id={id}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    disabled={disabled}
                    readOnly={readOnly}
                    placeholder={placeholder}
                />
                <Button
                    variant="outline"
                    disabled={disabled || readOnly}
                    onClick={() => {
                        setSearch("");
                        setOpen(true);
                    }}
                    aria-label={t("Choose font")}
                >
                    <Type aria-hidden="true" />
                    {t("Choose")}
                </Button>
            </div>
            <Dialog open={open} onOpenChange={setOpen} title={t("Choose font")}>
                <SearchInput
                    className="flex-1"
                    onClear={() => {
                        setSearch("");
                    }}
                    autoFocus
                    aria-label={t("Search fonts")}
                    placeholder={t("Search fonts")}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <p role="status" className="my-3 text-xs text-muted-foreground">
                    {t(":count results", { count: filtered.length })}
                </p>
                <div className="grid max-h-80 gap-1 overflow-auto sm:grid-cols-2">
                    {filtered.slice(0, 100).map((choice) => (
                        <Button
                            key={choice.value}
                            variant={
                                choice.value === value ? "secondary" : "ghost"
                            }
                            className="justify-start truncate"
                            onClick={() => {
                                onChange(choice.value);
                                setOpen(false);
                            }}
                        >
                            {choice.label}
                        </Button>
                    ))}
                </div>
                {filtered.length > 100 && (
                    <p className="mt-3 text-xs text-muted-foreground">
                        {t("Refine your search to see more fonts.")}
                    </p>
                )}
            </Dialog>
        </div>
    );
}
