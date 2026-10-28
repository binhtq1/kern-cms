import test from "node:test";
import assert from "node:assert/strict";
import { tableActionUrl } from "./table-query.ts";

test("bulk URL retains applied Unicode filters, locale and current pagination", () => {
    const result = new URL(
        tableActionUrl("https://cms.test/admin/pages/bulk?ref_lang=vi", {
            search: "Nghiệm thu & bài",
            status: "published",
            template: "default",
            sort: "name",
            direction: "asc",
            page: 2,
            per_page: 20,
        }),
    );
    assert.equal(result.pathname, "/admin/pages/bulk");
    assert.equal(result.searchParams.get("ref_lang"), "vi");
    assert.equal(result.searchParams.get("search"), "Nghiệm thu & bài");
    assert.equal(result.searchParams.get("page"), "2");
    assert.equal(result.searchParams.get("template"), "default");
});
test("registered advanced filters round trip without passing mutation fields or return URLs", () => {
    const result = new URL(
        tableActionUrl("https://cms.test/admin/posts/bulk", {
            advanced: [
                {
                    field: "name",
                    operator: "like",
                    value: "A & B",
                    injected: "ignored",
                },
            ],
            ids: [99],
            action: "delete",
            return_url: "https://other.test",
        }),
    );
    assert.equal(result.searchParams.get("advanced[0][value]"), "A & B");
    assert.deepEqual(
        [...result.searchParams.keys()],
        ["advanced[0][field]", "advanced[0][operator]", "advanced[0][value]"],
    );
});
test("clearing applied filters replaces stale query values while retaining a relative action route", () => {
    assert.equal(
        tableActionUrl(
            "/admin/pages/bulk?search=old&advanced[0][field]=name&ref_lang=vi",
            { search: "", advanced: [], page: 1 },
        ),
        "/admin/pages/bulk?ref_lang=vi&page=1",
    );
});
