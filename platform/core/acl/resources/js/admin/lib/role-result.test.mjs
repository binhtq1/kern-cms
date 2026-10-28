import test from "node:test";
import assert from "node:assert/strict";
import { assertRoleAssignment } from "./role-result.ts";

const fallback = "The role assignment response was invalid.";

test("only an explicit JSON success or the users ACL page props are accepted", () => {
    assert.doesNotThrow(() => assertRoleAssignment({ error: false }, fallback));
    assert.doesNotThrow(() =>
        assertRoleAssignment(
            { component: "ACL/Index", props: { kind: "users" } },
            fallback,
        ),
    );
});

test("wrong page and wrong props kind cannot mark an assignment successful", () => {
    for (const response of [
        { component: "ACL/Index", props: { kind: "roles" } },
        { component: "Users/Index", props: { kind: "users" } },
        { component: "ACL/Index", props: {} },
        { component: "ACL/Index" },
        {},
    ]) {
        assert.throws(
            () => assertRoleAssignment(response, fallback),
            (error) => error.message === fallback,
        );
    }
});

test("backend errors, flash errors, and even empty validation keys reject success", () => {
    assert.throws(
        () =>
            assertRoleAssignment(
                { error: true, message: "Permission denied" },
                fallback,
            ),
        /Permission denied/,
    );
    assert.throws(
        () =>
            assertRoleAssignment(
                {
                    component: "ACL/Index",
                    props: { kind: "users", flash: { error: "Save failed" } },
                },
                fallback,
            ),
        /Save failed/,
    );
    assert.throws(
        () =>
            assertRoleAssignment(
                {
                    component: "ACL/Index",
                    props: {
                        kind: "users",
                        errors: { role: ["Invalid role"] },
                    },
                },
                fallback,
            ),
        /Invalid role/,
    );
    assert.throws(() =>
        assertRoleAssignment(
            {
                component: "ACL/Index",
                props: { kind: "users", errors: { role: "" } },
            },
            fallback,
        ),
    );
});

test("malformed and incomplete result shapes are rejected rather than accepted as success", () => {
    for (const response of [
        null,
        undefined,
        "ok",
        { error: null },
        { component: "ACL/Index", props: { kind: null } },
    ]) {
        assert.throws(() => assertRoleAssignment(response, fallback));
    }
});
