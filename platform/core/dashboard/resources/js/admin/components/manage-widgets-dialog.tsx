import { Button } from "@admin/components/ui/button";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Dialog } from "@admin/components/ui/dialog";
import { useAdminTranslation } from "@admin/lib/translate";

import type { Dispatch, SetStateAction } from "react";
import type { Widget } from "../lib/dashboard-types";
export function ManageWidgetsDialog({
    manage,
    setManage,
    items,
    setItems,
    display,
    busy,
    error,
    save,
}: {
    manage: boolean;
    setManage: Dispatch<SetStateAction<boolean>>;
    items: Widget[];
    setItems: Dispatch<SetStateAction<Widget[]>>;
    display: Widget[];
    busy: boolean;
    error: string;
    save: () => Promise<void>;
}) {
    const t = useAdminTranslation();
    return (
        <Dialog
            open={manage}
            onOpenChange={(open) => {
                if (!busy) {
                    setManage(open);
                    if (!open) setItems(display);
                }
            }}
            title={t("Manage widgets")}
        >
            <div className="space-y-3">
                {items.map((item) => (
                    <label
                        key={item.name}
                        className={`flex items-center gap-3 rounded-md border p-3 text-sm ${item.visible ? "" : "text-muted-foreground"}`}
                    >
                        <Checkbox
                            disabled={busy}
                            checked={item.visible}
                            onCheckedChange={(checked) =>
                                setItems((old) =>
                                    old.map((widget) =>
                                        widget.name === item.name
                                            ? {
                                                  ...widget,
                                                  visible: checked === true,
                                              }
                                            : widget,
                                    ),
                                )
                            }
                        />
                        <span className={item.visible ? "" : "line-through"}>
                            {item.title}
                        </span>
                    </label>
                ))}
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => {
                            setItems(display);
                            setManage(false);
                        }}
                    >
                        {t("Cancel")}
                    </Button>
                    <Button loading={busy} onClick={() => void save()}>
                        {t("Save")}
                    </Button>
                </div>
            </div>
        </Dialog>
    );
}
