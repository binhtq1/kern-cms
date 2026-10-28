import { useAdminTranslation } from "@admin/lib/translate";
import { Head } from "@inertiajs/react";
import { Button } from "@admin/components/ui/button";
export default function Error({
    status,
    message,
}: {
    status: number;
    message?: string;
}) {
    const t = useAdminTranslation();
    const title =
        (
            {
                403: t("Access denied"),
                404: t("Page not found"),
                419: t("Your session has expired"),
                500: t("Something went wrong"),
                503: t("Temporarily unavailable"),
            } as Record<number, string>
        )[status] ?? t("Unable to complete this request");
    return (
        <main className="flex min-h-screen items-center justify-center p-6">
            <Head title={title} />
            <section className="w-full max-w-md rounded-lg border bg-card p-8">
                <p className="text-sm font-medium text-muted-foreground">
                    {status}
                </p>
                <h1 className="mt-3 text-2xl font-semibold">{title}</h1>
                <p className="mt-3 text-sm text-muted-foreground">
                    {message ??
                        t("Please try again or return to the previous page.")}
                </p>
                <Button className="mt-6" onClick={() => window.history.back()}>
                    {t("Go back")}
                </Button>
            </section>
        </main>
    );
}
