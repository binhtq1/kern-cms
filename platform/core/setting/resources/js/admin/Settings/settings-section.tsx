import type { ReactNode } from "react";
import { Button } from "@admin/components/ui/button";
import { CardFooter } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";

export function SettingsSaveFooter({
    processing,
    dirty,
    disabled,
    children,
}: {
    processing: boolean;
    dirty: boolean;
    disabled?: boolean;
    children?: ReactNode;
}) {
    const t = useAdminTranslation();
    return (
        <CardFooter className="flex-wrap">
            <Button type="submit" loading={processing} disabled={disabled}>
                {t("Save settings")}
            </Button>
            {dirty && (
                <span className="text-xs text-muted-foreground">
                    {t("Unsaved changes")}
                </span>
            )}
            {children && (
                <div className="ms-auto flex flex-wrap gap-2">{children}</div>
            )}
        </CardFooter>
    );
}

export function SettingsSection({
    title,
    description,
    navigation,
    children,
}: {
    title?: string;
    description?: ReactNode;
    navigation?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 items-start gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside className="min-w-0 space-y-4 lg:sticky lg:top-20">
                {title && <h2 className="text-sm font-semibold">{title}</h2>}
                {description && (
                    <div className="text-sm leading-6 text-muted-foreground">
                        {description}
                    </div>
                )}
                {navigation}
            </aside>
            <div className="min-w-0 space-y-5">{children}</div>
        </div>
    );
}
