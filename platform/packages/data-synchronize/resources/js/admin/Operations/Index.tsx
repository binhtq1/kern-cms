import { SearchInput } from "@admin/components/search-input";
import { Head, Link } from "@inertiajs/react";
import { useRef, useState } from "react";
import { Download, UploadCloud, ArrowRight, Search, X } from "lucide-react";
import AdminLayout from "@admin/layouts/admin-layout";
import { Button } from "@admin/components/ui/button";
import { Card, CardContent } from "@admin/components/ui/card";
import { Input } from "@admin/components/ui/input";
import { useAdminTranslation } from "@admin/lib/translate";
import { normalizeSearch } from "@admin/lib/utils";

type OperationSection = {
    id: string;
    title: string;
    items: {
        title: string;
        description: string;
        url: string;
        inertia: boolean;
    }[];
};

export default function DataSynchronize({
    title,
    sections,
}: {
    title: string;
    sections: OperationSection[];
}) {
    const t = useAdminTranslation();
    const [search, setSearch] = useState("");
    const searchInput = useRef<HTMLInputElement>(null);
    const query = normalizeSearch(search);
    const visible = sections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) =>
                normalizeSearch(
                    `${section.title} ${item.title} ${item.description}`,
                ).includes(query),
            ),
        }))
        .filter((section) => section.items.length);
    const clearSearch = () => {
        setSearch("");
        searchInput.current?.focus();
    };

    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {t(
                        "Export your data or import records using a CSV or Excel file.",
                    )}
                </p>
                <SearchInput
                    ref={searchInput}
                    className="w-full sm:w-80"
                    aria-label={t("Search data operations")}
                    placeholder={t("Search data operations")}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onClear={clearSearch}
                    onKeyDown={(event) => {
                        if (event.key === "Escape") clearSearch();
                    }}
                />
            </div>
            <div className="space-y-8">
                {visible.map((section) => {
                    const importing = section.id.endsWith("import");
                    const Icon = importing ? UploadCloud : Download;

                    return (
                        <section
                            key={section.id}
                            aria-labelledby={`${section.id}-title`}
                            className="grid items-start gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]"
                        >
                            <div className="space-y-2">
                                <h2
                                    id={`${section.id}-title`}
                                    className="flex items-center gap-2 text-base font-semibold"
                                >
                                    <Icon
                                        aria-hidden="true"
                                        className="size-4 text-primary"
                                    />
                                    {section.title}
                                </h2>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {t(
                                        importing
                                            ? "Choose a data type, check the sample file, then upload your records."
                                            : "Choose a data type to download, then select columns and a file format.",
                                    )}
                                </p>
                            </div>
                            <Card className="overflow-hidden divide-y">
                                {section.items.map((item) => {
                                    const OperationLink = item.inertia
                                        ? Link
                                        : "a";

                                    return (
                                        <OperationLink
                                            key={item.url}
                                            href={item.url}
                                            className="group flex min-w-0 items-center gap-4 p-5 outline-none transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <h3 className="text-sm font-semibold group-hover:text-primary">
                                                    {item.title}
                                                </h3>
                                                {item.description && (
                                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                )}
                                            </div>
                                            <ArrowRight
                                                aria-hidden="true"
                                                className="size-4 shrink-0 text-muted-foreground group-hover:text-primary"
                                            />
                                        </OperationLink>
                                    );
                                })}
                            </Card>
                        </section>
                    );
                })}
                {!visible.length && (
                    <Card>
                        <CardContent className="space-y-3 py-12 text-center">
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {t(
                                    search
                                        ? "No data operations found"
                                        : "No operations available",
                                )}
                            </p>
                            {search && (
                                <Button variant="outline" onClick={clearSearch}>
                                    {t("Clear search")}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
