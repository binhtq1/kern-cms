import assert from "node:assert/strict";
import { webcrypto } from "node:crypto";
import test from "node:test";
import { createUuid } from "./uuid.ts";

function withCrypto(value, run) {
    const descriptor = Object.getOwnPropertyDescriptor(globalThis, "crypto");
    Object.defineProperty(globalThis, "crypto", {
        configurable: true,
        value,
    });
    try {
        run();
    } finally {
        Object.defineProperty(globalThis, "crypto", descriptor);
    }
}

test("native UUID generation keeps its Crypto receiver", () => {
    const source = {
        randomUUID() {
            assert.equal(this, source);
            return "9a7e2910-5d7c-4e19-8f8e-b6ac44eb0407";
        },
        getRandomValues() {
            assert.fail("The native UUID method should be used when available");
        },
    };
    withCrypto(source, () => {
        assert.equal(createUuid(), "9a7e2910-5d7c-4e19-8f8e-b6ac44eb0407");
    });
});

test("HTTP contexts without randomUUID generate distinct v4 upload and UI identifiers", () => {
    const source = {
        getRandomValues(bytes) {
            assert.equal(this, source);
            return webcrypto.getRandomValues(bytes);
        },
    };
    withCrypto(source, () => {
        const identifiers = Array.from({ length: 1000 }, createUuid);
        assert.equal(new Set(identifiers).size, identifiers.length);
        for (const identifier of identifiers)
            assert.match(
                identifier,
                /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/,
            );
    });
});

test("fallback sets UUID version and variant for boundary random bytes", () => {
    for (const byte of [0, 255]) {
        withCrypto({ getRandomValues: (bytes) => bytes.fill(byte) }, () => {
            assert.equal(
                createUuid(),
                byte === 0
                    ? "00000000-0000-4000-8000-000000000000"
                    : "ffffffff-ffff-4fff-bfff-ffffffffffff",
            );
        });
    }
});

test("missing or failing secure randomness does not silently use weak identifiers", () => {
    withCrypto(undefined, () => {
        assert.throws(
            createUuid,
            /Secure random number generation is unavailable/,
        );
    });
    withCrypto(
        {
            getRandomValues() {
                throw new Error("Randomness failed");
            },
        },
        () => assert.throws(createUuid, /Randomness failed/),
    );
});
