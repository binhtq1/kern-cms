import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Input } from "@admin/components/ui/input";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";
export default function Search({
    keyword,
    url,
    results,
}: {
    keyword: string;
    url: string;
    results: { title: string; parents: string[]; url: string }[];
}) {
    const t = useAdminTranslation();
    const [search, setSearch] = useState(keyword);
    return (
        <AdminLayout title={t("Search")}>
            <Head title={t("Search")} />
            <form
                className="mb-5 flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(url, { keyword: search });
                }}
            >
                <Input
                    aria-label={t("Search")}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Button type="submit">{t("Search")}</Button>
            </form>
            <Card>
                <CardContent className="divide-y">
                    {results.map((result) => (
                        <a
                            key={result.url}
                            href={result.url}
                            className="block py-3 text-sm hover:text-primary-ink"
                        >
                            <span className="mr-2 text-muted-foreground">
                                {result.parents.join(" / ")}
                            </span>
                            {result.title}
                        </a>
                    ))}
                    {!results.length && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            {t("No results found")}
                        </p>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
