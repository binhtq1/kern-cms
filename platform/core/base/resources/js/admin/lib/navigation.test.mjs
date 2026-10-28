import test from "node:test";
import assert from "node:assert/strict";
import { activeNavigation } from "./navigation.ts";
const root = "https://cms.test/control/office";
const items = [
    { label: "Dashboard", url: root, active: true },
    {
        label: "Contact",
        children: [
            { label: "Contacts", url: `${root}/contacts`, active: true },
            { label: "Fields", url: `${root}/contacts/custom-fields` },
        ],
    },
    { label: "Pages", url: `${root}/pages` },
];
test("navigation activates only the most specific destination and its group", () => {
    const result = activeNavigation(
        items,
        `${root}/contacts/custom-fields/edit/4?return=${root}/pages`,
        root,
    );
    assert.equal(result[0].active, false);
    assert.equal(result[1].active, true);
    assert.deepEqual(
        result[1].children.map((item) => item.active),
        [false, true],
    );
    assert.equal(result[2].active, false);
    assert.equal(items[0].active, true);
});
test("navigation ignores query strings, trailing slashes and similarly named routes", () => {
    assert.equal(
        activeNavigation(items, `${root}/?page=2`, root)[0].active,
        true,
    );
    assert.equal(
        activeNavigation(items, `${root}/pages/edit/7`, root)[2].active,
        true,
    );
    assert.equal(
        activeNavigation(items, `${root}/pages-archive`, root).some(
            (item) => item.active,
        ),
        false,
    );
});

test("registry pages outside their section URL keep the owning sidebar item active", () => {
    const sections = [
        { label: "Settings", url: `${root}/settings` },
        { label: "System", url: `${root}/system` },
    ];
    for (const path of [
        "translations",
        "translations/theme",
        "translations/locales",
    ]) {
        assert.deepEqual(
            activeNavigation(sections, `${root}/${path}?ref_lang=vi`, root).map(
                (item) => item.active,
            ),
            [true, false],
        );
    }
    for (const path of ["audit-logs", "request-logs", "audit-logs/edit/3"]) {
        assert.deepEqual(
            activeNavigation(sections, `${root}/${path}`, root).map(
                (item) => item.active,
            ),
            [false, true],
        );
    }
    assert.ok(
        activeNavigation(sections, `${root}/translations-archive`, root).every(
            (item) => !item.active,
        ),
    );
});

test("an explicit navigation destination wins over registry section ownership", () => {
    const entries = [
        { label: "Settings", url: `${root}/settings` },
        { label: "Translations", url: `${root}/translations` },
        {
            label: "External",
            url: "https://other.test/control/office/translations/theme",
        },
    ];
    assert.deepEqual(
        activeNavigation(entries, `${root}/translations/theme`, root).map(
            (item) => item.active,
        ),
        [false, true, false],
    );
});

test("blog import and export belong to Tools unless explicitly registered", () => {
    const entries = [
        { label: "Posts", url: `${root}/blog/posts` },
        { label: "Data", url: `${root}/tools/data-synchronize` },
    ];
    for (const operation of ["import", "export"]) {
        assert.deepEqual(
            activeNavigation(
                entries,
                `${root}/blog/tools/data-synchronize/${operation}/posts`,
                root,
            ).map((item) => item.active),
            [false, true],
        );
    }
    assert.ok(
        activeNavigation(
            entries,
            `${root}/blog/tools/data-synchronize-archive`,
            root,
        ).every((item) => !item.active),
    );
    assert.deepEqual(
        activeNavigation(
            [...entries, { label: "Blog data", url: `${root}/blog/tools` }],
            `${root}/blog/tools/data-synchronize/export/posts`,
            root,
        ).map((item) => item.active),
        [false, false, true],
    );
});
