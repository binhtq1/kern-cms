import test from "node:test";
import assert from "node:assert/strict";
import axios from "axios";
import {
    adminResult,
    assertAdminSave,
    installAdminApi,
    isMediaList,
    isMediaPicker,
    isMenuReferences,
    isRecord,
    requirePayload,
} from "./admin-api.ts";
import { initializeAdminSession } from "./admin-session.ts";

test("runtime guards accept supported payload variants and reject malformed nested data", () => {
    assert.equal(isRecord({}), true);
    for (const invalid of [null, [], "object", 1])
        assert.equal(isRecord(invalid), false);

    const mediaList = {
        files: [
            {
                id: 1,
                name: "photo.jpg",
                url: "/media/photo.jpg",
                src: "/storage/photo.jpg",
            },
        ],
        folders: [{ id: "folder-2", name: "Uploads" }],
        breadcrumbs: [{ id: 0, name: "Root" }],
    };
    assert.equal(isMediaList(mediaList), true);
    assert.equal(
        isMediaList({ ...mediaList, files: [{ id: {}, name: "bad" }] }),
        false,
    );
    assert.equal(
        isMediaList({
            ...mediaList,
            files: [{ ...mediaList.files[0], is_folder: "false" }],
        }),
        false,
    );
    assert.equal(
        isMediaList({
            ...mediaList,
            files: [{ ...mediaList.files[0], full_url: 42 }],
        }),
        false,
    );
    assert.equal(
        isMediaList({
            ...mediaList,
            files: [{ ...mediaList.files[0], preview_url: false }],
        }),
        false,
    );
    assert.equal(isMediaList({ ...mediaList, folders: [null] }), false);
    assert.equal(isMediaList({ ...mediaList, breadcrumbs: "Root" }), false);

    const picker = {
        data: [
            {
                id: "file-1",
                name: "photo.jpg",
                url: "/media/photo",
                src: "/storage/photo",
            },
        ],
        current_page: 1,
        last_page: 3,
        folders: [],
        breadcrumbs: [{ id: "root", name: "Root" }],
    };
    assert.equal(isMediaPicker(picker), true);
    for (const invalid of [
        { ...picker, data: [{ ...picker.data[0], src: null }] },
        { ...picker, current_page: 0 },
        { ...picker, last_page: 1.5 },
        { ...picker, folders: [false] },
    ])
        assert.equal(isMediaPicker(invalid), false);

    const references = {
        type: "pages",
        current_page: 1,
        last_page: 1,
        total: 1,
        data: [
            {
                value: "5",
                label: "About",
                type: "page",
                id: "5",
                title: "About",
                url: "/about",
            },
        ],
    };
    assert.equal(isMenuReferences(references), true);
    for (const invalid of [
        { ...references, data: [{ ...references.data[0], value: 5 }] },
        { ...references, data: [{ value: "5", label: "About" }] },
        { ...references, total: -1 },
        { ...references, current_page: 0 },
        { ...references, data: [null] },
    ])
        assert.equal(isMenuReferences(invalid), false);
});

test("envelopes and save responses reject invalid data before consumers can replace state", () => {
    const payload = { records: [1, 2] };
    assert.deepEqual(
        adminResult({ error: false, data: payload, message: "ok" }, (value) =>
            Array.isArray(value.records),
        ),
        { data: payload, message: "ok" },
    );
    assert.throws(
        () =>
            adminResult({ error: false, data: { records: "bad" } }, (value) =>
                Array.isArray(value.records),
            ),
        /invalid response/,
    );
    assert.throws(
        () => adminResult({ error: false, data: payload }, () => false),
        /invalid response/,
    );
    assert.throws(() => adminResult({ data: payload }), /invalid response/);
    assert.throws(
        () =>
            adminResult({
                error: true,
                message: "permission denied",
                data: null,
            }),
        /permission denied/,
    );
    assert.throws(
        () => requirePayload({ ok: "yes" }, (value) => value.ok === true),
        /invalid response/,
    );

    assert.doesNotThrow(() =>
        assertAdminSave(
            {
                component: "Pages/Edit",
                props: { errors: {}, flash: { success: "Saved" } },
            },
            "Pages/Edit",
        ),
    );
    assert.doesNotThrow(() =>
        assertAdminSave({ error: false, data: { id: 1 } }, "Pages/Edit"),
    );
    assert.throws(
        () =>
            assertAdminSave(
                { component: "Pages/Index", props: { errors: {}, flash: {} } },
                "Pages/Edit",
            ),
        /invalid save response/,
    );
    assert.throws(
        () =>
            assertAdminSave(
                {
                    component: "Pages/Edit",
                    props: { errors: { title: ["Required"] }, flash: {} },
                },
                "Pages/Edit",
            ),
        /Required/,
    );
    assert.throws(
        () =>
            assertAdminSave(
                {
                    component: "Pages/Edit",
                    props: { errors: {}, flash: { error: "Denied" } },
                },
                "Pages/Edit",
            ),
        /Denied/,
    );
    assert.throws(
        () =>
            assertAdminSave(
                {
                    component: "Pages/Edit",
                    props: { errors: { title: [42] }, flash: {} },
                },
                "Pages/Edit",
            ),
        /invalid validation errors/,
    );
    assert.throws(
        () =>
            assertAdminSave(
                {
                    error: false,
                    data: {},
                    component: "Pages/Edit",
                    props: { errors: { title: ["Denied"] }, flash: {} },
                },
                "Pages/Edit",
            ),
        /Denied/,
    );
    assert.throws(
        () =>
            assertAdminSave(
                {
                    component: "Pages/Edit",
                    props: {
                        errors: { title: ["Title required"] },
                        flash: { error: "Save denied" },
                    },
                },
                "Pages/Edit",
            ),
        /Title required/,
        "validation errors take precedence over generic flash errors",
    );
    for (const errors of [{ title: "" }, { title: [] }]) {
        assert.throws(
            () =>
                assertAdminSave(
                    { component: "Pages/Edit", props: { errors, flash: {} } },
                    "Pages/Edit",
                ),
            /could not be saved/,
            "an error key with an empty message still means the save failed",
        );
    }
});

test("axios installation is idempotent and disposal ejects only its own interceptors", async () => {
    const client = axios.create({
        adapter: async (config) => ({
            data: config.headers.get("X-Admin-Draft-Context") ?? null,
            status: 200,
            statusText: "OK",
            headers: {},
            config,
        }),
    });
    globalThis.window = {
        location: {
            origin: "https://cms.example",
            href: "https://cms.example/admin/pages",
        },
    };
    globalThis.document = {
        cookie: "XSRF-TOKEN=fresh-token",
        querySelector: () => null,
    };
    initializeAdminSession({
        context: "stable-context",
        loginUrl: "https://cms.example/admin/login",
        statusUrl: "https://cms.example/admin/session/status",
    });

    const dispose = installAdminApi(client);
    assert.strictEqual(installAdminApi(client), dispose);
    const response = await client.post("https://cms.example/admin/pages", {
        _token: "stale",
        title: "Draft",
    });
    assert.equal(response.data, "stable-context");
    const requestConfig = response.config;
    assert.equal(requestConfig.headers.get("X-XSRF-TOKEN"), "fresh-token");
    assert.equal(
        requestConfig.headers.get("X-Admin-Draft-Context"),
        "stable-context",
    );
    assert.deepEqual(JSON.parse(requestConfig.data), { title: "Draft" });

    dispose();
    dispose();
    const outside = await client.post("https://other.example/admin/pages", {
        _token: "untouched",
    });
    assert.equal(
        outside.config.headers.get("X-Admin-Draft-Context"),
        undefined,
    );
    assert.deepEqual(JSON.parse(outside.config.data), { _token: "untouched" });
});
