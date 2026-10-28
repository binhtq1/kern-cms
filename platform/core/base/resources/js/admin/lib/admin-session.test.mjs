import test from "node:test";
import assert from "node:assert/strict";
import {
    adminSession,
    initializeAdminSession,
    adminMutationUrl,
    draftVisitOptions,
    handleSessionException,
    matchingSessionStatus,
    withoutStaleCsrf,
} from "./admin-session.ts";
import { currentCsrfHeaders } from "./admin-session.ts";
const origin = "https://cms.example";
const session = {
    context: "synthetic-original-context",
    loginUrl: `${origin}/admin/login`,
    statusUrl: `${origin}/admin/session/status`,
};
initializeAdminSession(session);
globalThis.window = {
    location: { origin, href: `${origin}/admin/pages/edit/10` },
};
function exception(
    status = 401,
    data = {
        code: "admin_session_expired",
        loginUrl: session.loginUrl,
        statusUrl: session.statusUrl,
    },
    headers = { "x-admin-session-recovery": "1" },
) {
    return new CustomEvent("inertia:httpException", {
        cancelable: true,
        detail: { response: { status, data, headers } },
    });
}
test("recovery cancels the HTTP exception exactly once without converting it to success", () => {
    const event = exception();
    let expired = 0;
    assert.equal(
        handleSessionException(
            event,
            session,
            origin,
            () => expired++,
            () => assert.fail("not forbidden"),
        ),
        undefined,
    );
    assert.equal(event.defaultPrevented, true);
    assert.equal(expired, 1);
    assert.equal(
        handleSessionException(
            event,
            session,
            origin,
            () => expired++,
            () => {},
        ),
        undefined,
    );
    assert.equal(expired, 1);
});
test("unmarked, malformed and cross-origin errors retain normal handling", () => {
    for (const event of [
        exception(500),
        exception(401, null),
        exception(401, {
            code: "admin_session_expired",
            loginUrl: "https://other.example/login",
            statusUrl: session.statusUrl,
        }),
        exception(401, {
            code: "admin_session_expired",
            loginUrl: session.loginUrl,
            statusUrl: `${origin}/admin/other`,
        }),
        exception(401, {}, {}),
    ]) {
        assert.equal(
            handleSessionException(
                event,
                session,
                origin,
                () => assert.fail("invalid recovery"),
                () => assert.fail("invalid forbidden"),
            ),
            undefined,
        );
        assert.equal(event.defaultPrevented, false);
    }
});
test("permission denial preserves the draft and never opens the login dialog", () => {
    const event = exception(403, {}, { "x-admin-draft-forbidden": "1" });
    let denied = 0;
    assert.equal(
        handleSessionException(
            event,
            session,
            origin,
            () => assert.fail("not expired"),
            () => denied++,
        ),
        undefined,
    );
    assert.equal(event.defaultPrevented, true);
    assert.equal(denied, 1);
});
test("the original identity remains fixed and headers stay on admin mutation URLs", () => {
    initializeAdminSession({ ...session, context: "another-account" });
    assert.equal(adminSession().context, session.context);
    for (const url of [
        `${origin}/admin/pages/edit/10`,
        "/admin/media/picker-upload",
    ])
        assert.equal(adminMutationUrl(url, origin), true);
    for (const url of [
        "https://outside.example/admin/pages",
        `${origin}/administrator`,
        `${origin}/contact/send`,
        "javascript:alert(1)",
    ]) {
        assert.equal(adminMutationUrl(url, origin), false);
        assert.deepEqual(
            draftVisitOptions(url, { method: "post", data: {} }),
            {},
        );
    }
    assert.deepEqual(draftVisitOptions("/admin/pages", { method: "get" }), {});
    const options = draftVisitOptions("/admin/pages", {
        method: "post",
        headers: { Existing: "value" },
        data: { _token: "stale", name: "Draft" },
    });
    assert.deepEqual(options, {
        headers: {
            Existing: "value",
            "X-Admin-Draft-Context": session.context,
        },
        data: { name: "Draft" },
    });
});
test("only a successful protected probe explicitly matching the actor permits resume", () => {
    assert.equal(matchingSessionStatus({ ok: true }, { matches: true }), true);
    for (const data of [{ matches: false }, { matches: "true" }, null, {}])
        assert.equal(matchingSessionStatus({ ok: true }, data), false);
    assert.equal(
        matchingSessionStatus({ ok: false }, { matches: true }),
        false,
    );
});
test("CSRF headers use the latest cookie, retain fallback, and never leak externally", () => {
    assert.equal(
        currentCsrfHeaders("XSRF-TOKEN=first%2Btoken", "old-meta")[
            "X-XSRF-TOKEN"
        ],
        "first+token",
    );
    assert.equal(
        currentCsrfHeaders("XSRF-TOKEN=rotated", "old-meta")["X-XSRF-TOKEN"],
        "rotated",
    );
    assert.equal(currentCsrfHeaders("", "meta")["X-CSRF-TOKEN"], "meta");
    assert.equal(
        currentCsrfHeaders("XSRF-TOKEN=%bad", "meta")["X-CSRF-TOKEN"],
        "meta",
    );
    assert.deepEqual(
        currentCsrfHeaders(
            "XSRF-TOKEN=secret",
            "meta",
            "https://outside.example/upload",
        ),
        {},
    );
    assert.deepEqual(
        currentCsrfHeaders(
            "XSRF-TOKEN=fresh",
            "meta",
            `${origin}/ajax/render-ui-blocks`,
        ),
        { "X-XSRF-TOKEN": "fresh" },
    );
    const data = new FormData();
    data.append("_token", "old");
    data.append("name", "Draft");
    assert.equal(withoutStaleCsrf(data).has("_token"), false);
    assert.equal(data.get("name"), "Draft");
});

test("a scoped server failure retains the draft without opening sign-in or claiming success", () => {
    const event = exception(500, {}, { "x-admin-draft-error": "1" });
    let failed = 0;
    assert.equal(
        handleSessionException(
            event,
            session,
            origin,
            () => assert.fail("not expired"),
            () => assert.fail("not forbidden"),
            () => failed++,
        ),
        undefined,
    );
    assert.equal(event.defaultPrevented, true);
    assert.equal(failed, 1);
});

test("Inertia's false-return convention does not swallow ordinary HTTP errors", () => {
    for (const status of [401, 403, 404, 500, 503]) {
        const event = exception(
            status,
            {
                component: "System/Compatibility",
                props: { message: "Unsupported React admin integration" },
            },
            {},
        );
        const result = handleSessionException(
            event,
            session,
            origin,
            () => assert.fail("not a recovery response"),
            () => assert.fail("not a scoped permission response"),
            () => assert.fail("not a scoped draft failure"),
        );
        // Match Inertia's global event listener wrapper.
        if (event.cancelable && !event.defaultPrevented && result === false)
            event.preventDefault();
        assert.equal(result, undefined);
        assert.equal(
            event.defaultPrevented,
            false,
            `HTTP ${status} must remain visible`,
        );
    }
});

test("guest entry captures the first authenticated actor and retains that identity across later visits", async () => {
    const fresh = await import(`./admin-session.ts?guest-entry=${Date.now()}`);
    fresh.initializeAdminSession(undefined);
    fresh.initializeAdminSession(null);
    assert.equal(fresh.adminSession(), null);
    assert.deepEqual(fresh.draftContextHeaders(), {});
    fresh.initializeAdminSession(session);
    assert.deepEqual(fresh.adminSession(), session);
    fresh.initializeAdminSession({
        ...session,
        context: "different-actor-context",
    });
    fresh.initializeAdminSession(undefined);
    assert.deepEqual(fresh.adminSession(), session);
    assert.deepEqual(fresh.draftContextHeaders(), {
        "X-Admin-Draft-Context": session.context,
    });
});
