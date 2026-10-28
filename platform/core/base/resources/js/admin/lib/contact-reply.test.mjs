import test from "node:test";
import assert from "node:assert/strict";
import { completedContactReply } from "./contact-reply.ts";

test("transport failure or an unconfirmed response leaves the reply draft and baseline intact", () => {
    for (const flash of [
        undefined,
        {},
        { error: "Unable to send email" },
        { success: "Stale success", error: "Unable to send email" },
    ]) {
        assert.equal(
            completedContactReply(
                flash,
                "<p>Original reply</p>",
                "<p>Original reply</p>",
            ),
            null,
        );
        assert.equal(
            completedContactReply(
                flash,
                "<p>Original reply</p>",
                "<p>Newer draft</p>",
            ),
            null,
        );
    }
});

test("confirmed delivery clears only the submitted reply and keeps newer text", () => {
    const flash = { success: "Message sent successfully" };
    assert.deepEqual(
        completedContactReply(flash, "<p>Sent</p>", "<p>Sent</p>"),
        { message: "" },
    );
    assert.deepEqual(
        completedContactReply(flash, "<p>Sent</p>", "<p>Next reply</p>"),
        { message: "<p>Next reply</p>" },
    );
});
