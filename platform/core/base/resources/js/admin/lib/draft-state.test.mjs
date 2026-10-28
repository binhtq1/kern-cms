import test from "node:test";
import assert from "node:assert/strict";
import { isSchemaRefresh } from "./draft-state.ts";
test("only same-record custom field refreshes can bypass navigation warnings", () => {
    const current = "https://cms.test/admin/pages/edit/1?ref_lang=en";
    const refresh = {
        only: ["customFieldGroups"],
        preserveState: true,
        url: new URL(`${current}&template=full-width`),
    };
    assert.equal(isSchemaRefresh(refresh, current), true);
    for (const update of [
        { url: new URL("https://cms.test/admin/pages/edit/2?ref_lang=en") },
        { url: new URL("https://cms.test/admin/pages/edit/1?ref_lang=vi") },
        {
            url: new URL(
                "https://cms.test/admin/pages/edit/1?ref_lang=en&ref_from=2",
            ),
        },
        { only: ["page"] },
        { preserveState: false },
        { only: [] },
    ])
        assert.equal(
            isSchemaRefresh({ ...refresh, ...update }, current),
            false,
        );
});
