import {
    RegisteredBulkChange,
    type RegisteredField,
} from "@admin/components/registered-table-controls";
import { Button } from "@admin/components/ui/button";
import { useAdminTranslation } from "@admin/lib/translate";

import type { Dispatch, SetStateAction } from "react";
import type { Row } from "../lib/acl-types";
export function AclBulkActions({
    selected,
    canStatus,
    busy,
    items,
    setError,
    setBulkAction,
    bulkFields,
    bulkField,
    bulkValue,
    setBulkField,
    setBulkValue,
    setSelected,
}: {
    selected: number[];
    canStatus: boolean;
    busy: boolean;
    items: {
        data: Row[];
        total: number;
        current_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    setError: Dispatch<SetStateAction<string>>;
    setBulkAction: Dispatch<SetStateAction<string | null>>;
    bulkFields: RegisteredField[];
    bulkField: string;
    bulkValue: string;
    setBulkField: Dispatch<SetStateAction<string>>;
    setBulkValue: Dispatch<SetStateAction<string>>;
    setSelected: Dispatch<SetStateAction<number[]>>;
}) {
    const t = useAdminTranslation();
    return (
        <div className="flex flex-wrap items-center gap-2 border-b bg-muted/30 p-3">
            <span className="text-sm">
                {selected.length} {t("selected")}
            </span>
            {canStatus &&
                ["activated", "deactivated"].map((status) => (
                    <Button
                        key={status}
                        size="sm"
                        variant="outline"
                        disabled={
                            busy ||
                            !selected.every(
                                (id) =>
                                    items.data.find((row) => row.id === id)
                                        ?.can_status,
                            )
                        }
                        onClick={() => {
                            setError("");
                            setBulkAction(status);
                        }}
                    >
                        {t(status === "activated" ? "Activate" : "Deactivate")}
                    </Button>
                ))}
            {selected.every(
                (id) => items.data.find((row) => row.id === id)?.can_change,
            ) && (
                <RegisteredBulkChange
                    fields={bulkFields}
                    name={bulkField}
                    value={bulkValue}
                    onNameChange={setBulkField}
                    onValueChange={setBulkValue}
                    busy={busy}
                    onApply={() => {
                        setError("");
                        setBulkAction("change");
                    }}
                />
            )}
            <Button
                size="sm"
                variant="destructive"
                disabled={
                    busy ||
                    !selected.every(
                        (id) =>
                            items.data.find((row) => row.id === id)?.delete_url,
                    )
                }
                onClick={() => {
                    setError("");
                    setBulkAction("delete");
                }}
            >
                {t("Delete")}
            </Button>
            <Button
                size="sm"
                variant="ghost"
                disabled={busy}
                onClick={() => setSelected([])}
            >
                {t("Clear selection")}
            </Button>
        </div>
    );
}
