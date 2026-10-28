import test from "node:test";
import assert from "node:assert/strict";
import {
    setupStepPayload,
    setupHasPendingChanges,
    setupExitCookie,
} from "./setup-state.ts";
const fields = { primary_color: true, primary_font: false };
const data = {
    site_title: "Site",
    logo: "logo.png",
    favicon: "favicon.png",
    admin_logo: "admin.png",
    admin_favicon: "admin-favicon.png",
    primary_color: "#245443",
    primary_font: "Arial",
    username: "admin",
    email: "admin@example.test",
    password: "",
    password_confirmation: "",
};
test("setup branding step only submits supported fields and stays on the step after a later image change", () => {
    const submitted = setupStepPayload(data, 2, fields);
    assert.equal("password" in submitted, false);
    assert.equal("primary_font" in submitted, false);
    assert.equal(submitted.primary_color, "#245443");
    assert.equal(
        setupHasPendingChanges(
            submitted,
            { ...data, logo: "unsent-logo.png" },
            2,
            fields,
        ),
        true,
    );
    assert.equal(
        setupHasPendingChanges(
            submitted,
            { ...data, username: "unrelated" },
            2,
            fields,
        ),
        false,
    );
});
test("setup account step protects unsent passwords and ignores already-saved branding", () => {
    const submitted = setupStepPayload(data, 3, fields);
    assert.deepEqual(Object.keys(submitted), [
        "username",
        "email",
        "password",
        "password_confirmation",
    ]);
    assert.equal(
        setupHasPendingChanges(
            submitted,
            { ...data, password: "unsent" },
            3,
            fields,
        ),
        true,
    );
    assert.equal(
        setupHasPendingChanges(
            submitted,
            { ...data, primary_color: "#ffffff" },
            3,
            fields,
        ),
        false,
    );
    assert.equal(setupHasPendingChanges(submitted, data, 3, fields), false);
});

test("exiting setup temporarily dismisses it with the legacy one-day host cookie", () => {
    assert.equal(
        setupExitCookie("cms.example.test", new Date("2026-10-16T10:00:00Z")),
        "get_started_cookie=1;expires=Sat, 17 Oct 2026 10:00:00 GMT;domain=cms.example.test;path=/",
    );
});
