import test from "node:test";
import assert from "node:assert/strict";
import { contactLink } from "./contact-link.ts";
test("contact detail links use fixed schemes and encode address parameters", () => {
    assert.equal(
        contactLink("email", "person@example.com"),
        "mailto:person@example.com",
    );
    assert.equal(
        contactLink("phone", "+84 (123) 456-789"),
        "tel:%2B84%20(123)%20456-789",
    );
    assert.equal(
        contactLink("email", "user?subject=bad@example.com"),
        "mailto:user%3Fsubject%3Dbad@example.com",
    );
});
test("contact detail rejects executable, malformed and header-injection values", () => {
    for (const [kind, value] of [
        ["email", "javascript:alert(1)"],
        ["email", "person@example.com\r\nBcc:attacker@test.com"],
        ["phone", "123\n456"],
        ["phone", "javascript:alert(1)"],
        ["phone", "abc"],
        ["email", "<script>@example.com"],
    ])
        assert.equal(contactLink(kind, value), null);
});
