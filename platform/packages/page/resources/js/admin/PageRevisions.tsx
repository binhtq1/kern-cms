import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import { useAdminDate } from "@admin/lib/date";
import { Card } from "@admin/components/ui/card";
import { useAdminTranslation } from "@admin/lib/translate";

export type PageRevision = {
    id: string;
    field: string;
    label: string;
    old_value: string | null;
    new_value: string | null;
    author: string | null;
    created_at: string | null;
};

export default function PageRevisions({
    revisions,
}: {
    revisions: PageRevision[];
}) {
    const t = useAdminTranslation();
    const formatDate = useAdminDate();

    return (
        <Card className="overflow-hidden">
            <div className="border-b p-5">
                <h2 className="font-semibold">{t("Revision history")}</h2>
            </div>
            {revisions.length ? (
                <div className="overflow-x-auto">
                    <Table className="w-full text-left text-sm">
                        <TableHeader className="border-b bg-muted/30 text-xs text-muted-foreground">
                            <TableRow>
                                {[
                                    "Author",
                                    "Field",
                                    "Before",
                                    "After",
                                    "Created",
                                ].map((label) => (
                                    <TableHead
                                        key={label}
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        {t(label)}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {revisions.map((revision) => (
                                <TableRow
                                    key={revision.id}
                                    className="border-b align-top last:border-b-0"
                                >
                                    <TableCell className="px-4 py-3">
                                        {revision.author ?? t("Unknown user")}
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
                                        {t(revision.label)}
                                    </TableCell>
                                    <TableCell className="max-w-sm break-words whitespace-pre-wrap px-4 py-3">
                                        <del className="text-red-800 dark:text-red-300">
                                            {revision.old_value ?? t("Empty")}
                                        </del>
                                    </TableCell>
                                    <TableCell className="max-w-sm break-words whitespace-pre-wrap px-4 py-3">
                                        <ins className="text-emerald-800 dark:text-emerald-300">
                                            {revision.new_value ?? t("Empty")}
                                        </ins>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap px-4 py-3">
                                        {revision.created_at ? (
                                            <time
                                                dateTime={revision.created_at}
                                            >
                                                {formatDate(
                                                    revision.created_at,
                                                    true,
                                                )}
                                            </time>
                                        ) : (
                                            t("Unknown")
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            ) : (
                <p className="p-5 text-sm text-muted-foreground">
                    {t("No revisions yet.")}
                </p>
            )}
        </Card>
    );
}
