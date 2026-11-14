import { Link, router } from "@inertiajs/react";
import { useEffect, useState, type FormEvent } from "react";
import { Button } from "@admin/components/ui/button";
import { Input } from "@admin/components/ui/input";
import { Select } from "@admin/components/ui/select";
import { Checkbox } from "@admin/components/ui/checkbox";
import { Dialog } from "@admin/components/ui/dialog";
import { Card, CardContent } from "@admin/components/ui/card";
import { Field } from "@admin/components/field";
import { TablePagination } from "@admin/components/table-pagination";
import { useAdminTranslation } from "@admin/lib/translate";
import {
    MemberLayout,
    MemberErrors,
    useMember,
    useMemberMutation,
} from "./shared";
type Post = {
    id: string;
    name: string;
    image?: string;
    status: string;
    statusLabel: string;
    categories: string[];
    created_at: string;
    edit: string;
    delete: string;
};
type Filters = {
    search?: string;
    category?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
};
type Posts = {
    data: Post[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export default function Posts({
    posts,
    filters,
    categories,
    statuses,
}: {
    posts: Posts;
    filters: Filters;
    categories: { id: number; name: string }[];
    statuses: Record<string, string>;
}) {
    const { urls } = useMember();
    const t = useAdminTranslation();
    const mutation = useMemberMutation();
    const [values, setValues] = useState<Filters>(filters);
    const [selected, setSelected] = useState<string[]>([]);
    const [target, setTarget] = useState<Post | "bulk" | null>(null);
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState(false);
    const [bulkField, setBulkField] = useState("name");
    const [bulkValue, setBulkValue] = useState("");
    function updateSelected() {
        void mutation.run(
            urls.bulkUpdatePosts!,
            { ids: selected, field: bulkField, value: bulkValue },
            "POST",
            () => {
                setEditing(false);
                setSelected([]);
                router.reload({ only: ["posts"] });
            },
        );
    }
    useEffect(() => {
        setValues(filters);
        setSelected([]);
    }, [filters, posts]);
    function visit(changes: Filters = {}) {
        router.get(
            urls.posts!,
            Object.fromEntries(
                Object.entries({ ...values, ...changes }).filter(
                    ([, value]) => value !== "" && value !== undefined,
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }
    function search(event: FormEvent) {
        event.preventDefault();
        visit();
    }
    function remove() {
        if (!target) return;
        void mutation.run(
            target === "bulk" ? urls.bulkPosts! : target.delete,
            target === "bulk" ? { ids: selected } : {},
            target === "bulk" ? "POST" : "DELETE",
            () => {
                setTarget(null);
                setSelected([]);
                router.reload({ only: ["posts"] });
            },
        );
    }
    const exportUrl = new URL(urls.posts!, window.location.origin);
    for (const [key, value] of Object.entries(filters))
        if (value !== undefined && value !== "")
            exportUrl.searchParams.set(key, String(value));
    exportUrl.searchParams.set("export", "csv");
    return (
        <MemberLayout
            title={t("Posts")}
            actions={
                <Button asChild>
                    <Link href={urls.createPost!}>{t("Create post")}</Link>
                </Button>
            }
        >
            <Card>
                <CardContent>
                    <form
                        className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        onSubmit={search}
                    >
                        <Field label={t("Search")}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={values.search ?? ""}
                                    maxLength={120}
                                    onChange={(event) =>
                                        setValues((current) => ({
                                            ...current,
                                            search: event.target.value,
                                        }))
                                    }
                                />
                            )}
                        </Field>
                        <Field label={t("Status")}>
                            {(id) => (
                                <Select
                                    id={id}
                                    value={values.status ?? ""}
                                    onValueChange={(status) =>
                                        setValues((current) => ({
                                            ...current,
                                            status,
                                        }))
                                    }
                                >
                                    <option value="">
                                        {t("All statuses")}
                                    </option>
                                    {Object.entries(statuses).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </Select>
                            )}
                        </Field>
                        <Field label={t("Category")}>
                            {(id) => (
                                <Select
                                    id={id}
                                    value={String(values.category ?? "")}
                                    onValueChange={(category) =>
                                        setValues((current) => ({
                                            ...current,
                                            category,
                                        }))
                                    }
                                >
                                    <option value="">
                                        {t("All categories")}
                                    </option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </Field>
                        <div className="flex gap-2">
                            <Button type="submit" loading={loading}>
                                {t("Filter")}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={loading}
                                onClick={() => {
                                    setValues({});
                                    router.get(urls.posts!);
                                }}
                            >
                                {t("Reset")}
                            </Button>
                        </div>
                        <Field label={t("From date")}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="date"
                                    value={values.date_from ?? ""}
                                    onChange={(event) =>
                                        setValues((current) => ({
                                            ...current,
                                            date_from: event.target.value,
                                        }))
                                    }
                                />
                            )}
                        </Field>
                        <Field label={t("To date")}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="date"
                                    value={values.date_to ?? ""}
                                    onChange={(event) =>
                                        setValues((current) => ({
                                            ...current,
                                            date_to: event.target.value,
                                        }))
                                    }
                                />
                            )}
                        </Field>
                    </form>
                </CardContent>
            </Card>
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                    <Button
                        variant="outline"
                        disabled={!selected.length || mutation.busy}
                        onClick={() => {
                            mutation.setErrors({});
                            setEditing(true);
                        }}
                    >
                        {t("Edit selected")} ({selected.length})
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={!selected.length || mutation.busy}
                        onClick={() => setTarget("bulk")}
                    >
                        {t("Delete selected")} ({selected.length})
                    </Button>
                    <Button variant="outline" asChild disabled={!posts.total}>
                        <a href={exportUrl.toString()}>
                            {t("Export filtered posts")}
                        </a>
                    </Button>
                </div>
                <div className="overflow-x-auto">
                    <table
                        className="w-full text-start text-sm"
                        aria-busy={loading}
                    >
                        <thead>
                            <tr className="border-b bg-muted/40">
                                <th className="w-12 p-3">
                                    <Checkbox
                                        aria-label={t(
                                            "Select all posts on this page",
                                        )}
                                        checked={
                                            !!posts.data.length &&
                                            selected.length ===
                                                posts.data.length
                                        }
                                        disabled={loading}
                                        onCheckedChange={(checked) =>
                                            setSelected(
                                                checked
                                                    ? posts.data.map(
                                                          (post) => post.id,
                                                      )
                                                    : [],
                                            )
                                        }
                                    />
                                </th>
                                {[
                                    ["name", "Name"],
                                    ["categories", "Categories"],
                                    ["status", "Status"],
                                    ["created_at", "Created at"],
                                ].map(([key, label]) => (
                                    <th
                                        key={key}
                                        className="p-3 text-start"
                                        aria-sort={
                                            filters.sort === key
                                                ? filters.direction === "asc"
                                                    ? "ascending"
                                                    : "descending"
                                                : undefined
                                        }
                                    >
                                        {key === "categories" ? (
                                            t(label)
                                        ) : (
                                            <button
                                                className="min-h-11 text-start font-semibold"
                                                disabled={loading}
                                                onClick={() =>
                                                    visit({
                                                        sort: key,
                                                        direction:
                                                            filters.sort ===
                                                                key &&
                                                            filters.direction ===
                                                                "asc"
                                                                ? "desc"
                                                                : "asc",
                                                    })
                                                }
                                            >
                                                {t(label)}{" "}
                                                {filters.sort === key
                                                    ? filters.direction ===
                                                      "asc"
                                                        ? "↑"
                                                        : "↓"
                                                    : ""}
                                            </button>
                                        )}
                                    </th>
                                ))}
                                <th className="p-3 text-start">
                                    {t("Actions")}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {posts.data.length ? (
                                posts.data.map((post) => (
                                    <tr key={post.id} className="border-b">
                                        <td className="p-3">
                                            <Checkbox
                                                aria-label={`${t("Select")} ${post.name}`}
                                                checked={selected.includes(
                                                    post.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  post.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      post.id,
                                                              ),
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center gap-3">
                                                {post.image && (
                                                    <img
                                                        src={post.image}
                                                        alt=""
                                                        className="size-12 rounded object-cover"
                                                    />
                                                )}
                                                <Link
                                                    href={post.edit}
                                                    className="font-medium text-primary-ink hover:underline"
                                                >
                                                    {post.name}
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            {post.categories.join(", ")}
                                        </td>
                                        <td className="p-3">
                                            {post.statusLabel}
                                        </td>
                                        <td className="p-3">
                                            <time dateTime={post.created_at}>
                                                {post.created_at}
                                            </time>
                                        </td>
                                        <td className="p-3">
                                            <div className="flex gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link href={post.edit}>
                                                        {t("Edit")}
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setTarget(post)
                                                    }
                                                >
                                                    {t("Delete")}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="p-8 text-center">
                                        {t("No posts found")}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <TablePagination
                    total={posts.total}
                    currentPage={posts.current_page}
                    lastPage={posts.last_page}
                    perPage={posts.per_page}
                    previous={posts.prev_page_url}
                    next={posts.next_page_url}
                    loading={loading}
                    onPageSize={(per_page) => visit({ per_page })}
                    onVisit={(url) =>
                        router.visit(url, {
                            preserveState: true,
                            preserveScroll: true,
                        })
                    }
                />
            </Card>
            <Dialog
                open={!!target}
                onOpenChange={(open) =>
                    !mutation.busy && !open && setTarget(null)
                }
                title={t("Delete posts")}
                description={t("This action cannot be undone.")}
                closeDisabled={mutation.busy}
                footer={
                    <>
                        <Button
                            variant="outline"
                            disabled={mutation.busy}
                            onClick={() => setTarget(null)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            variant="destructive"
                            loading={mutation.busy}
                            onClick={remove}
                        >
                            {t("Delete")}
                        </Button>
                    </>
                }
            >
                <MemberErrors errors={mutation.errors} />
                <p className="py-3">
                    {target === "bulk"
                        ? `${t("Selected posts")}: ${selected.length}`
                        : target?.name}
                </p>
            </Dialog>
            <Dialog
                open={editing}
                onOpenChange={(open) => !mutation.busy && setEditing(open)}
                title={t("Edit selected posts")}
                closeDisabled={mutation.busy}
                footer={
                    <>
                        <Button
                            variant="outline"
                            disabled={mutation.busy}
                            onClick={() => setEditing(false)}
                        >
                            {t("Cancel")}
                        </Button>
                        <Button
                            loading={mutation.busy}
                            disabled={!bulkValue || !selected.length}
                            onClick={updateSelected}
                        >
                            {t("Save changes")}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4 py-4">
                    <Field label={t("Field")}>
                        {(id) => (
                            <Select
                                id={id}
                                value={bulkField}
                                disabled={mutation.busy}
                                onValueChange={(field) => {
                                    setBulkField(field);
                                    setBulkValue(
                                        field === "status" ? "pending" : "",
                                    );
                                }}
                            >
                                <option value="name">{t("Name")}</option>
                                <option value="status">{t("Status")}</option>
                                <option value="created_at">
                                    {t("Created at")}
                                </option>
                            </Select>
                        )}
                    </Field>
                    <Field label={t("Value")} error={mutation.errors.value}>
                        {(id) =>
                            bulkField === "status" ? (
                                <Select
                                    id={id}
                                    value={bulkValue}
                                    disabled={mutation.busy}
                                    onValueChange={setBulkValue}
                                >
                                    <option value="pending">
                                        {statuses.pending}
                                    </option>
                                    <option value="draft">
                                        {statuses.draft}
                                    </option>
                                </Select>
                            ) : (
                                <Input
                                    id={id}
                                    type={
                                        bulkField === "created_at"
                                            ? "date"
                                            : "text"
                                    }
                                    value={bulkValue}
                                    maxLength={250}
                                    disabled={mutation.busy}
                                    onChange={(event) =>
                                        setBulkValue(event.target.value)
                                    }
                                />
                            )
                        }
                    </Field>
                    <MemberErrors errors={mutation.errors} />
                </div>
            </Dialog>
        </MemberLayout>
    );
}
