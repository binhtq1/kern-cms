import test from "node:test";
import assert from "node:assert/strict";
import {
    restoreMediaNavigation,
    persistMediaNavigation,
    defaultMediaNavigation,
    mediaFolderUnavailable,
} from "./media-navigation.ts";
import { mediaDocumentPreview } from "./media-document-preview.ts";
test("media navigation reload retains supported display choices and resets when the application key changes", () => {
    const navigation = {
        folder: "12",
        view: "all_media",
        sort: "size-desc",
        filter: "image",
        grid: false,
    };
    const saved = persistMediaNavigation(navigation, "actor1:hash1");
    assert.deepEqual(restoreMediaNavigation(saved, "actor1:hash1"), navigation);
    assert.deepEqual(
        restoreMediaNavigation(saved, "actor1:hash2"),
        defaultMediaNavigation,
    );
    assert.deepEqual(
        restoreMediaNavigation(saved, "actor2:hash1"),
        defaultMediaNavigation,
    );
    assert.deepEqual(Object.keys(JSON.parse(saved).navigation).sort(), [
        "filter",
        "folder",
        "grid",
        "sort",
        "view",
    ]);
});
test("malformed storage and unsupported choices recover safely without retaining arbitrary request parameters", () => {
    for (const raw of [null, "bad json", "null", "[]", '{"version":9}'])
        assert.deepEqual(
            restoreMediaNavigation(raw, "key"),
            defaultMediaNavigation,
        );
    const raw = JSON.stringify({
        version: 1,
        app_key: "key",
        navigation: {
            folder: "../x",
            view: "bad",
            sort: "DROP TABLE",
            filter: {},
            grid: "false",
            search: "old search",
            page: 99,
        },
    });
    assert.deepEqual(
        restoreMediaNavigation(raw, "key"),
        defaultMediaNavigation,
    );
    assert.equal(
        restoreMediaNavigation(
            persistMediaNavigation(
                { ...defaultMediaNavigation, view: "recent", folder: "12" },
                "key",
            ),
            "key",
        ).folder,
        "0",
    );
});
test("missing or forbidden saved folders recover to root while network errors and valid empty folders keep their context", () => {
    assert.equal(mediaFolderUnavailable("12", 403), true);
    assert.equal(mediaFolderUnavailable("12", 404), true);
    assert.equal(mediaFolderUnavailable("12", 200, [{ id: 0 }]), true);
    assert.equal(
        mediaFolderUnavailable("12", 200, [{ id: 0 }, { id: 12 }]),
        false,
    );
    assert.equal(mediaFolderUnavailable("12", 500), false);
    assert.equal(mediaFolderUnavailable(0, 403), false);
});
test("document preview uses only the server-provided gated URL and respects popup mode", () => {
    const origin = "https://cms.example";
    assert.deepEqual(
        mediaDocumentPreview(
            { type: "document", preview_url: "/storage/file.pdf" },
            origin,
        ),
        { url: `${origin}/storage/file.pdf`, popup: false },
    );
    assert.deepEqual(
        mediaDocumentPreview(
            {
                type: "document",
                preview_url: "https://viewer.example/doc",
                preview_type: "popup",
            },
            origin,
        ),
        { url: "https://viewer.example/doc", popup: true },
    );
    for (const preview_url of [
        null,
        "",
        "javascript:alert(1)",
        "data:text/html,x",
        "https://actor:secret@example.com/x",
    ])
        assert.equal(
            mediaDocumentPreview({ type: "document", preview_url }, origin),
            null,
        );
    assert.equal(
        mediaDocumentPreview(
            { type: "image", preview_url: "/file.png" },
            origin,
        ),
        null,
    );
});
