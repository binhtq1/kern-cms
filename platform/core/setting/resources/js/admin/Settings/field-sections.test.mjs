import test from "node:test";
import assert from "node:assert/strict";
import { fieldSections, isSettingFieldVisible } from "./field-sections.ts";
test("media groups retain every field once, including conditional provider secrets", () => {
    const fields = [
        { name: "media_driver" },
        {
            name: "media_aws_secret",
            conditions: [{ name: "media_driver", value: "s3" }],
        },
        { name: "media_watermark_source" },
        { name: "media_sizes_large_width" },
        { name: "media_upload_path" },
        { name: "media_image_quality" },
    ];
    const result = fieldSections(fields, "media");
    assert.deepEqual(
        result.find((group) => group.key === "storage").fields,
        fields.slice(0, 2),
    );
    assert.equal(
        result.find((group) => group.key === "watermark").fields[0],
        fields[2],
    );
    assert.equal(
        result.find((group) => group.key === "thumbnails").fields[0],
        fields[3],
    );
    assert.deepEqual(
        new Set(result.flatMap((group) => group.fields)),
        new Set(fields),
    );
});
test("mail connection and sender stay distinct while other settings keep existing field order", () => {
    const fields = [
        { name: "email_driver" },
        { name: "email_from_address" },
        { name: "email_password" },
    ];
    assert.deepEqual(
        fieldSections(fields, "email").map((group) =>
            group.fields.map((field) => field.name),
        ),
        [["email_driver", "email_password"], ["email_from_address"]],
    );
    assert.deepEqual(fieldSections(fields)[0].fields, fields);
});

test("conditional image-size fields disappear together without hiding their enabling toggle", () => {
    const width = {
        name: "media_sizes_thumb_width",
        conditions: [{ name: "media_enable_thumbnail_sizes", value: "1" }],
    };
    const height = { ...width, name: "media_sizes_thumb_height" };
    for (const disabled of [0, "0", false]) {
        assert.equal(
            [width, height].some((field) =>
                isSettingFieldVisible(field, {
                    media_enable_thumbnail_sizes: disabled,
                }),
            ),
            false,
        );
    }
    for (const enabled of [1, "1", true]) {
        assert.equal(
            [width, height].every((field) =>
                isSettingFieldVisible(field, {
                    media_enable_thumbnail_sizes: enabled,
                }),
            ),
            true,
        );
    }
    assert.equal(
        isSettingFieldVisible(
            { name: "media_enable_thumbnail_sizes" },
            { media_enable_thumbnail_sizes: 0 },
        ),
        true,
    );
    assert.equal(
        isSettingFieldVisible(
            {
                name: "s3_key",
                conditions: [{ name: "media_driver", value: "s3" }],
            },
            { media_driver: "local" },
        ),
        false,
    );
});
