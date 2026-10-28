import test from "node:test";
import assert from "node:assert/strict";
import {
    contentOptionIdentity,
    updateContentOption,
    moveContentOption,
} from "./content-option-state.ts";

test("saved ID 2 and a new third option retain distinct identities through editing and reordering", () => {
    const first = { id: "1", label: "First", value: "first", order: 0 };
    const second = { id: "2", label: "Second", value: "second", order: 1 };
    const added = { label: "Third", value: "third", order: 2 };
    const original = [first, second, added];
    const before = JSON.stringify(original);
    const identities = original.map(contentOptionIdentity);
    assert.equal(new Set(identities).size, 3);
    assert.equal(JSON.stringify(original), before);
    const edited = updateContentOption(added, { label: "Tiếng Việt" });
    assert.equal(contentOptionIdentity(edited), identities[2]);
    const moved = moveContentOption([first, second, edited], 2, 1);
    assert.equal(moved.length, 3);
    assert.deepEqual(moved.map(contentOptionIdentity), [
        identities[0],
        identities[2],
        identities[1],
    ]);
    assert.equal(moved[1], edited);
    const removed = moved.filter((option) => option !== first);
    const payload = JSON.parse(
        JSON.stringify(removed.map((option, order) => ({ ...option, order }))),
    );
    assert.deepEqual(payload, [
        { label: "Tiếng Việt", value: "third", order: 0 },
        { id: "2", label: "Second", value: "second", order: 1 },
    ]);
    assert.equal(JSON.stringify(original), before);
});

test("arrow moves preserve saved IDs and option data and reject stale indexes", () => {
    const options = [
        { id: "1", label: "Default", value: "default", order: 0 },
        { id: "2", label: "Translated label", value: "other", order: 1 },
    ];
    const moved = moveContentOption(options, 1, 0);
    assert.equal(moved[0], options[1]);
    assert.deepEqual(moveContentOption(moved, 0, 1), options);
    for (const [from, to] of [
        [-1, 0],
        [0, -1],
        [2, 0],
        [0, 2],
        [1, 1],
    ])
        assert.equal(moveContentOption(options, from, to), options);
});
