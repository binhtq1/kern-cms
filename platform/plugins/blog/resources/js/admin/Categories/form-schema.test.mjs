import test from "node:test";
import assert from "node:assert/strict";
import { isCategoryFormSchema } from "./form-schema.ts";

const schema = () => ({
    title: "Categories",
    resource: "categories",
    id: null,
    values: { name: "", parent_id: "0", seo_meta: {} },
    fields: [{ name: "name", label: "Name", type: "text", choices: [] }],
    urls: {
        index: "/admin/blog/categories",
        submit: "/admin/blog/categories/create",
        media: "/admin/media/picker",
        mediaUpload: "/admin/media/picker-upload",
    },
    permissions: { create: true, edit: true },
    translatable: false,
    slugEnabled: true,
    seoEnabled: true,
    galleryEnabled: false,
    languages: [],
    gallery: [],
    customFieldGroups: [],
    shortcodes: [],
    fieldOptions: [],
    replies: [],
});

test("category panel accepts structured create and translated edit forms", () => {
    assert.equal(isCategoryFormSchema(schema()), true);
    assert.equal(
        isCategoryFormSchema({
            ...schema(),
            id: "8",
            language: "vi",
            translatable: true,
            languages: [
                {
                    name: "Tiếng Việt",
                    active: true,
                    url: "/edit/8?ref_lang=vi",
                },
            ],
        }),
        true,
    );
});

test("category panel rejects fallback HTML, legacy responses and incomplete schemas", () => {
    for (const response of [
        undefined,
        null,
        "<form>Blade fallback</form>",
        { error: false, data: "<form>Legacy AJAX</form>" },
        {},
        [],
        { ...schema(), resource: "posts" },
        { ...schema(), fields: [] },
        { ...schema(), fields: [{ name: "name", choices: null }] },
        { ...schema(), urls: { submit: "/create" } },
        { ...schema(), languages: [{ name: "Việt" }] },
        { ...schema(), permissions: null },
        { ...schema(), gallery: undefined },
    ]) {
        assert.equal(isCategoryFormSchema(response), false);
    }
});
