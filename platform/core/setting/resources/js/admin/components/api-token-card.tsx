import { ArrowRight, KeyRound } from "lucide-react";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";

export function ApiTokenCard({ url }: { url: string }) {
    const t = useAdminTranslation();

    return (
        <Card>
            <CardContent className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex min-w-0 flex-1 basis-64 items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/40 text-muted-foreground">
                        <KeyRound aria-hidden="true" className="size-5" />
                    </span>
                    <div className="min-w-0 space-y-1">
                        <h2 className="text-sm font-semibold">
                            {t("API tokens")}
                        </h2>
                        <p className="max-w-xl text-sm leading-relaxed text-muted-foreground">
                            {t(
                                "Create and revoke access tokens for applications connected to your API.",
                            )}
                        </p>
                    </div>
                </div>
                <Button variant="outline" className="w-full sm:w-auto" asChild>
                    <a href={url}>
                        {t("Manage API tokens")}
                        <ArrowRight
                            aria-hidden="true"
                            className="rtl:rotate-180"
                        />
                    </a>
                </Button>
            </CardContent>
        </Card>
    );
}
