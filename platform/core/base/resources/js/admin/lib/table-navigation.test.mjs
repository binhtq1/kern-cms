import test from "node:test";
import assert from "node:assert/strict";
import { createTableNavigation } from "./table-navigation.ts";

function harness() {
    const visits = [];
    const loading = [];
    let canceled = 0;
    const navigation = createTableNavigation(
        (url, data, options) => {
            visits.push({ url, data, options });
            options.onCancelToken({ cancel: () => canceled++ });
            options.onStart({});
        },
        (value) => loading.push(value),
    );
    return { navigation, visits, loading, canceled: () => canceled };
}

test("table navigation guards duplicate clicks and preserves query options", () => {
    const { navigation, visits, loading } = harness();
    let finished = 0;
    navigation.get(
        "/admin/posts",
        { page: 2 },
        {
            preserveScroll: true,
            onFinish: () => finished++,
        },
    );
    navigation.get("/admin/posts", { page: 3 });
    assert.equal(visits.length, 1);
    assert.deepEqual(visits[0].data, { page: 2 });
    assert.equal(visits[0].options.preserveScroll, true);
    assert.deepEqual(loading, [true]);
    visits[0].options.onFinish({});
    assert.equal(finished, 1);
    assert.deepEqual(loading, [true, false]);
    navigation.get("/admin/posts", { page: 3 });
    assert.equal(visits.length, 2);
});

test("canceling on unmount suppresses stale finish without clearing a new request", () => {
    const { navigation, visits, loading, canceled } = harness();
    navigation.get("/admin/posts");
    navigation.cancel();
    assert.equal(canceled(), 1);
    navigation.get("/admin/posts", { page: 2 });
    visits[0].options.onFinish({});
    assert.deepEqual(loading, [true, true]);
    visits[1].options.onFinish({});
    assert.deepEqual(loading, [true, true, false]);
});

test("a before-vetoed visit and a synchronous error release the click guard", () => {
    const loading = [];
    let calls = 0;
    const navigation = createTableNavigation(
        () => {
            calls++;
            if (calls === 2) throw new Error("transport failed");
        },
        (value) => loading.push(value),
    );
    navigation.get("/admin/posts");
    assert.throws(() => navigation.get("/admin/posts"), /transport failed/);
    navigation.get("/admin/posts");
    assert.equal(calls, 3);
    assert.deepEqual(loading, [false, false, false]);
});

test("server validation errors retain their callback and finish clears loading", () => {
    const { navigation, visits, loading } = harness();
    let error;
    navigation.get(
        "/admin/posts",
        {},
        {
            onError: (errors) => {
                error = errors;
            },
        },
    );
    visits[0].options.onError({ search: "Invalid filter" });
    visits[0].options.onFinish({});
    assert.deepEqual(error, { search: "Invalid filter" });
    assert.deepEqual(loading, [true, false]);
});

test("late callbacks cannot resurrect loading or hijack a newer cancellation token", () => {
    const { navigation, visits, loading, canceled } = harness();
    navigation.get("/admin/posts");
    navigation.cancel();
    navigation.get("/admin/posts", { page: 2 });
    visits[0].options.onStart({});
    let staleCanceled = false;
    visits[0].options.onCancelToken({
        cancel: () => {
            staleCanceled = true;
        },
    });
    assert.equal(staleCanceled, true);
    assert.deepEqual(loading, [true, true]);
    navigation.cancel();
    assert.equal(canceled(), 2);
});
