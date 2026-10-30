import test from "node:test";
import assert from "node:assert/strict";
import {
    savedFieldGroupState,
    fieldItemIdentity,
    updateFieldItem,
    moveFieldItem,
    normalizeFieldSlug,
} from "./field-group-state.ts";
import { draftSnapshot } from "../../../../../../core/base/resources/js/admin/lib/draft-state.ts";

const field = (slug, id = null, items = []) => ({
    id,
    title: slug,
    slug,
    type: items.length ? "repeater" : "date",
    instructions: "",
    options: {},
    items,
});

test("repeated save-and-continue submissions retain persisted nested IDs and order", () => {
    const saved = {
        items: [field("date", 11), field("repeater", 12, [field("time", 13)])],
        rules: [[{ name: "page", type: "==", value: "1" }]],
    };
    const first = savedFieldGroupState(saved);
    const payload = JSON.parse(JSON.stringify(first.items));
    assert.deepEqual(
        payload.map((item) => item.id),
        ["11", "12"],
    );
    assert.equal(payload[1].items[0].id, "13");
    const second = savedFieldGroupState({ ...saved, items: payload });
    assert.deepEqual(second, first);
    assert.equal(second.items.length, 2);
    assert.equal(second.items[1].items.length, 1);
    assert.equal(draftSnapshot(second), draftSnapshot(first));
    assert.equal(saved.items[0].id, 11);
});

test("successful reconciliation drops deleted IDs and marks only returned tree clean", () => {
    const draft = {
        items: [field("new"), field("removed", "7")],
        rules: [],
        deleted: ["8"],
    };
    const saved = savedFieldGroupState({
        items: [field("new", "9")],
        rules: [],
    });
    assert.deepEqual(saved.deleted, []);
    assert.deepEqual(
        saved.items.map((item) => item.id),
        ["9"],
    );
    assert.notEqual(draftSnapshot(draft), draftSnapshot(saved));
    const changed = { ...saved, items: [...saved.items, field("another")] };
    assert.notEqual(draftSnapshot(changed), draftSnapshot(saved));
});

test("validation or absent success props leave unsaved values and deletion queue intact", () => {
    const draft = { items: [field("draft")], rules: [], deleted: ["8"] };
    for (const result of [
        savedFieldGroupState(
            { items: [field("old", "1")] },
            { group_items: "Invalid" },
        ),
        savedFieldGroupState(undefined),
        savedFieldGroupState({ items: [field("old", "1")] }, {}, true),
    ]) {
        const next = result ?? draft;
        assert.equal(next, draft);
        assert.equal(next.items[0].id, null);
        assert.deepEqual(next.deleted, ["8"]);
    }
});

test("unsaved field identities survive editing and nested sibling reordering without entering the payload", () => {
    const a = field("repeat", null, [field("first"), field("second")]);
    const b = field("other");
    const identity = fieldItemIdentity(a);
    const child = fieldItemIdentity(a.items[0]);
    const snapshot = draftSnapshot([a, b]);
    fieldItemIdentity(b);
    assert.equal(draftSnapshot([a, b]), snapshot);
    const edited = updateFieldItem(a, {
        title: "Updated",
        options: { buttonLabel: "Add entry" },
    });
    assert.equal(fieldItemIdentity(edited), identity);
    const nested = moveFieldItem(
        edited.items,
        child,
        fieldItemIdentity(edited.items[1]),
        true,
    );
    const updated = updateFieldItem(edited, { items: nested });
    const reordered = moveFieldItem(
        [updated, b],
        identity,
        fieldItemIdentity(b),
        true,
    );
    assert.equal(reordered[1], updated);
    assert.equal(fieldItemIdentity(reordered[1]), identity);
    assert.equal(fieldItemIdentity(reordered[1].items[1]), child);
    assert.deepEqual(reordered[1].options, { buttonLabel: "Add entry" });
    const payload = JSON.parse(JSON.stringify(reordered));
    assert.deepEqual(
        payload[1].items.map((item) => item.slug),
        ["second", "first"],
    );
    assert.deepEqual(Object.keys(payload[1]).sort(), Object.keys(a).sort());
    assert.equal(payload[1].id, null);
    assert.equal(a.items[0].slug, "first");
});

test("field sorting cannot move a field into another parent's list", () => {
    const a = field("a", null, [field("nested")]);
    const b = field("b");
    const items = [a, b];
    assert.equal(
        moveFieldItem(
            items,
            fieldItemIdentity(a),
            fieldItemIdentity(a.items[0]),
        ),
        items,
    );
    assert.equal(
        moveFieldItem(
            a.items,
            fieldItemIdentity(b),
            fieldItemIdentity(a.items[0]),
        ),
        a.items,
    );
    assert.equal(
        moveFieldItem(items, fieldItemIdentity(a), fieldItemIdentity(a)),
        items,
    );
    assert.deepEqual(
        moveFieldItem(items, fieldItemIdentity(b), fieldItemIdentity(a)).map(
            (item) => item.slug,
        ),
        ["b", "a"],
    );
});

test("field slugs follow legacy Vietnamese transliteration and underscore separators", () => {
    assert.equal(
        normalizeFieldSlug("Điện thoại liên hệ"),
        "dien_thoai_lien_he",
    );
    assert.equal(normalizeFieldSlug("Custom Slug!"), "custom_slug");
    assert.equal(normalizeFieldSlug("custom_key"), "custom_key");
    assert.equal(normalizeFieldSlug("--Một--Tên--"), "_mot_ten_");
});
