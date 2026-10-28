import assert from "node:assert/strict";
import { test } from "node:test";
import { createPageRegistry, registeredPagePath } from "./page-registry.ts";

const root = "../../platform/core/acl/resources/js/admin/";
const registration = `${root}registration.json`;
const pages = [`${root}Index.tsx`, `${root}Auth/Form.tsx`];

test("package registrations preserve existing page names and resolve nested auth pages", () => {
    const registry = createPageRegistry(
        {
            [registration]: {
                version: 1,
                namespaces: { ACL: "", Auth: "Auth" },
            },
        },
        pages,
    );
    assert.equal(registeredPagePath(registry, "ACL/Index"), pages[0]);
    assert.equal(registeredPagePath(registry, "Auth/Form"), pages[1]);
    assert.throws(
        () => registeredPagePath(registry, "Auth/Missing"),
        /not registered/,
    );
    assert.throws(
        () => registeredPagePath(registry, "toString"),
        /not registered/,
    );
    assert.throws(
        () => registeredPagePath(registry, "Auth/../Index"),
        /not registered/,
    );
});

test("registration cannot escape its package or silently shadow another namespace", () => {
    for (const directory of [
        "../acl",
        "/Auth",
        "Auth//Form",
        "Auth/../Form",
        "components",
        "_components",
        "hooks",
        "lib",
        "Screens/lib",
    ]) {
        assert.throws(
            () =>
                createPageRegistry(
                    {
                        [registration]: {
                            version: 1,
                            namespaces: { Auth: directory },
                        },
                    },
                    pages,
                ),
            /Invalid/,
        );
    }
    assert.throws(
        () =>
            createPageRegistry(
                {
                    "../../platform/plugins/../registration.json": {
                        version: 1,
                        namespaces: { Auth: "Auth" },
                    },
                },
                pages,
            ),
        /Invalid/,
    );
    assert.throws(
        () =>
            createPageRegistry(
                {
                    [registration]: {
                        version: 1,
                        namespaces: { Auth: "Auth" },
                    },
                    "../../platform/plugins/extra/resources/js/admin/registration.json":
                        { version: 1, namespaces: { Auth: "Auth" } },
                },
                pages,
            ),
        /Duplicate/,
    );
});

test("an extension exposes only its bundled files without root resolver changes", () => {
    const extension = "../../platform/plugins/extra/resources/js/admin/";
    const registry = createPageRegistry(
        {
            [`${extension}registration.json`]: {
                version: 1,
                namespaces: { Extra: "Screens" },
            },
        },
        [
            `${extension}Screens/Index.tsx`,
            `${root}Index.tsx`,
            `${extension}Other.tsx`,
        ],
    );
    assert.deepEqual(Object.keys(registry), ["Extra/Index"]);
    assert.equal(
        registeredPagePath(registry, "Extra/Index"),
        `${extension}Screens/Index.tsx`,
    );
});

test("root and nested extension namespaces exclude support directories but preserve page casing", () => {
    const extension = "../../platform/plugins/extra/resources/js/admin/";
    const registry = createPageRegistry(
        {
            [`${extension}registration.json`]: {
                version: 1,
                namespaces: { Extra: "", Screens: "Screens" },
            },
        },
        [
            `${extension}Index.tsx`,
            `${extension}Screens/mixedCase/Detail.tsx`,
            `${extension}Components/Index.tsx`,
            ...["components", "_components", "hooks", "lib"].flatMap(
                (directory) => [
                    `${extension}${directory}/Internal.tsx`,
                    `${extension}Screens/${directory}/Internal.tsx`,
                ],
            ),
        ],
    );
    assert.deepEqual(Object.keys(registry), [
        "Extra/Index",
        "Extra/Screens/mixedCase/Detail",
        "Extra/Components/Index",
        "Screens/mixedCase/Detail",
    ]);
});

test("Composer runtime pages resolve alongside site-owned plugin pages", () => {
    const runtime =
        "../../vendor/binhtq1/kern-core/core/acl/resources/js/admin/";
    const plugin = "../../platform/plugins/customer/resources/js/admin/";
    const registry = createPageRegistry(
        {
            [`${runtime}registration.json`]: {
                version: 1,
                namespaces: { Auth: "" },
            },
            [`${plugin}registration.json`]: {
                version: 1,
                namespaces: { Customer: "" },
            },
        },
        [`${runtime}Login.tsx`, `${plugin}Index.tsx`],
    );
    assert.equal(
        registeredPagePath(registry, "Auth/Login"),
        `${runtime}Login.tsx`,
    );
    assert.equal(
        registeredPagePath(registry, "Customer/Index"),
        `${plugin}Index.tsx`,
    );
    assert.throws(
        () =>
            createPageRegistry(
                {
                    "../../vendor/another/package/core/acl/resources/js/admin/registration.json":
                        { version: 1, namespaces: { Unsafe: "" } },
                },
                [],
            ),
        /Invalid admin registration/,
    );
});
