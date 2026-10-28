import test from "node:test";
import assert from "node:assert/strict";
import { downloadError, downloadFilename } from "./download.ts";

test("failed blob downloads retain server validation messages instead of HTTP status text", async () => {
    assert.equal(
        await downloadError(
            new Blob([
                JSON.stringify({ message: "Select at least one column" }),
            ]),
            "Export failed",
        ),
        "Select at least one column",
    );
    assert.equal(
        await downloadError(
            new Blob(["<html>Session expired</html>"]),
            "Export failed",
        ),
        "Export failed",
    );
    assert.equal(
        await downloadError({ message: "Not authorized" }, "Export failed"),
        "Not authorized",
    );
});

test("download filenames support encoded unicode, quoted separators and malformed header fallback", () => {
    assert.equal(
        downloadFilename(
            "attachment; filename*=UTF-8''b%C3%A1o-c%C3%A1o.xlsx",
            "export.xlsx",
        ),
        "báo-cáo.xlsx",
    );
    assert.equal(
        downloadFilename(
            'attachment; filename="report; final.csv"',
            "export.csv",
        ),
        "report; final.csv",
    );
    assert.equal(
        downloadFilename(
            "attachment; filename*=UTF-8''bad%ZZ; filename=report.csv",
            "export.csv",
        ),
        "report.csv",
    );
    assert.equal(downloadFilename(undefined, "example.csv"), "example.csv");
});
