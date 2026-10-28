import test from "node:test";
import assert from "node:assert/strict";
import {
    findHtmlLoss,
    findVisualHtmlLoss,
    normalizeHtmlWhitespace,
} from "./rich-editor-safety.ts";

const element = (tag, attributes = {}, children = []) => ({
    tag,
    attributes,
    children,
});

test("visual guard accepts supported styles and table attributes without discarding them", () => {
    const source = [
        element("table", { style: "border: 1px solid red;", border: "1" }, [
            element("tr", {}, [
                element(
                    "td",
                    {
                        colspan: "2",
                        style: "background-color: rgb(255, 0, 0);",
                    },
                    [
                        element(
                            "span",
                            { style: "font-family: Georgia; font-size: 24px;" },
                            ["Heading"],
                        ),
                    ],
                ),
            ]),
        ]),
    ];
    assert.equal(findHtmlLoss(source, structuredClone(source)), null);
});

test("visual guard permits only the known editor-generated table defaults", () => {
    assert.equal(
        findHtmlLoss(
            [
                element("table", {}, [
                    element("tr", {}, [element("td", {}, ["A"])]),
                ]),
            ],
            [
                element("table", { style: "min-width: 25px;" }, [
                    element("tr", {}, [
                        element("td", { colspan: "1", rowspan: "1" }, ["A"]),
                    ]),
                ]),
            ],
        ),
        null,
    );
    assert.equal(
        findHtmlLoss([element("td")], [element("td", { colspan: "2" })]),
        "<td> colspan",
    );
    assert.equal(
        findHtmlLoss(
            [element("table")],
            [element("table", { style: "width: 800px;" })],
        ),
        "<table> style",
    );
});

test("visual guard refuses dropped classes, IDs, custom attributes and styles", () => {
    for (const [name, value] of [
        ["class", "legacy-banner"],
        ["id", "anchor"],
        ["data-widget", "hero"],
        ["style", "display: grid;"],
    ]) {
        assert.equal(
            findHtmlLoss(
                [element("p", { [name]: value }, ["Text"])],
                [element("p", {}, ["Text"])],
            ),
            `<p> ${name}`,
        );
    }
});

test("visual guard refuses dropped wrappers, captions and comments", () => {
    assert.equal(
        findHtmlLoss(
            [element("section", {}, [element("p", {}, ["Text"])])],
            [element("p", {}, ["Text"])],
        ),
        "<section>",
    );
    assert.equal(
        findHtmlLoss(
            [
                element("figure", {}, [
                    element("img"),
                    element("figcaption", {}, ["Caption"]),
                ]),
            ],
            [element("img")],
        ),
        "<figure>",
    );
    assert.equal(
        findHtmlLoss([element("HTML comment or custom node")], []),
        "HTML structure",
    );
});

test("visual guard refuses changed shortcode text or attributes", () => {
    assert.equal(
        findHtmlLoss(
            [element("p", {}, ['[hero image="one.jpg"]Hello[/hero]'])],
            [element("p", {}, ['[hero image="two.jpg"]Hello[/hero]'])],
        ),
        "text or embedded content",
    );
});

test("visual guard permits default link attributes but preserves an explicit target and rel", () => {
    assert.equal(
        findHtmlLoss(
            [element("a", { href: "/page" }, ["Page"])],
            [
                element(
                    "a",
                    {
                        href: "/page",
                        target: "_blank",
                        rel: "noopener noreferrer nofollow",
                    },
                    ["Page"],
                ),
            ],
        ),
        null,
    );
    assert.equal(
        findHtmlLoss(
            [element("a", { href: "/page", target: "_self" })],
            [element("a", { href: "/page", target: "_blank" })],
        ),
        "<a> target",
    );
});

test("visual guard accepts generated YouTube markup only if its attributes survive", () => {
    const video = [
        element("div", { "data-youtube-video": "" }, [
            element("iframe", {
                src: "https://www.youtube-nocookie.com/embed/abc123",
                width: "640",
                height: "480",
            }),
        ]),
    ];
    assert.equal(findHtmlLoss(video, structuredClone(video)), null);
    const changed = structuredClone(video);
    changed[0].children[0].attributes.src =
        "https://www.youtube-nocookie.com/embed/other";
    assert.equal(findHtmlLoss(video, changed), "<iframe> src");
});

test("formatting whitespace between blocks is ignored while inline/preformatted spaces are retained", () => {
    const first = element("p", {}, ["First"]);
    const second = element("p", {}, ["Second"]);
    assert.deepEqual(
        normalizeHtmlWhitespace(["\n", first, "\n  ", second, "\n"]),
        [first, second],
    );
    const inline = [
        element("strong", {}, ["A"]),
        " ",
        element("em", {}, ["B"]),
    ];
    assert.deepEqual(normalizeHtmlWhitespace(inline, "p"), inline);
    const code = ["\n  ", first, "\n"];
    assert.deepEqual(normalizeHtmlWhitespace(code, "pre"), code);
    assert.deepEqual(
        normalizeHtmlWhitespace(["\n", element("tr"), "\n"], "table"),
        [element("tr")],
    );
});

test("visual mode accepts compact, canonical and nested list paragraphs without changing text", () => {
    for (const tag of ["ul", "ol"]) {
        const attributes = tag === "ol" ? { start: "3" } : {};
        const compact = [
            element(tag, attributes, [
                element("li", {}, [
                    "First",
                    element("ul", {}, [element("li", {}, ["Nested"])]),
                ]),
            ]),
        ];
        const canonical = [
            element(tag, attributes, [
                element("li", {}, [
                    element("p", {}, ["First"]),
                    element("ul", {}, [
                        element("li", {}, [element("p", {}, ["Nested"])]),
                    ]),
                ]),
            ]),
        ];
        assert.equal(
            findVisualHtmlLoss(compact, [...canonical, element("p")]),
            null,
        );
        assert.equal(
            findVisualHtmlLoss(canonical, [...canonical, element("p")]),
            null,
        );
        const changed = structuredClone(canonical);
        changed[0].children[0].children[0].children = ["Changed"];
        assert.notEqual(
            findVisualHtmlLoss(compact, [...changed, element("p")]),
            null,
        );
    }
});

test("visual mode accepts the paragraph required inside a plain-text quote", () => {
    const compact = [element("blockquote", {}, ["Quote stays"])];
    const canonical = [
        element("blockquote", {}, [element("p", {}, ["Quote stays"])]),
    ];
    assert.equal(findVisualHtmlLoss(compact, canonical), null);
    assert.notEqual(
        findVisualHtmlLoss(compact, [
            element("blockquote", {}, [element("p", {}, ["Changed quote"])]),
        ]),
        null,
    );
    assert.notEqual(
        findVisualHtmlLoss(compact, [
            element("blockquote", {}, [
                element("p", { class: "legacy" }, ["Quote stays"]),
            ]),
        ]),
        null,
    );
});

test("list normalization preserves paragraph attributes, boundaries and unknown wrappers", () => {
    const list = (children) => [
        element("ul", {}, [element("li", {}, children)]),
    ];
    for (const attributes of [
        { class: "legacy" },
        { "data-unknown": "keep" },
        { style: "color: red;" },
    ]) {
        assert.notEqual(
            findVisualHtmlLoss(
                list([element("p", attributes, ["Text"])]),
                list([element("p", {}, ["Text"])]),
            ),
            null,
        );
    }
    assert.notEqual(
        findVisualHtmlLoss(
            list([element("div", {}, ["Text"])]),
            list([element("p", {}, ["Text"])]),
        ),
        null,
    );
    assert.notEqual(
        findVisualHtmlLoss(
            list([element("p", {}, ["First"]), element("p", {}, ["Second"])]),
            list([element("p", {}, ["FirstSecond"])]),
        ),
        null,
    );
    assert.notEqual(
        findVisualHtmlLoss(
            list([element("p", {}, ["First"]), "Second"]),
            list([element("p", {}, ["FirstSecond"])]),
        ),
        null,
    );
    assert.notEqual(
        findVisualHtmlLoss(
            list([element("p"), element("p", {}, ["Text"])]),
            list([element("p", {}, ["Text"])]),
        ),
        null,
    );
});

test("only one generated empty trailing paragraph is allowed after a non-paragraph block", () => {
    const list = element("ul", {}, [element("li", {}, ["Text"])]);
    assert.equal(findVisualHtmlLoss([list], [list, element("p")]), null);
    for (const suffix of [
        [element("p"), element("p")],
        [element("p", { class: "extra" })],
        [element("p", {}, ["Extra"])],
        [element("div")],
    ]) {
        assert.notEqual(findVisualHtmlLoss([list], [list, ...suffix]), null);
    }
    assert.notEqual(
        findVisualHtmlLoss(
            [element("p", {}, ["Text"])],
            [element("p", {}, ["Text"]), element("p")],
        ),
        null,
    );
    assert.notEqual(findVisualHtmlLoss([list, element("p")], [list]), null);
    assert.notEqual(findVisualHtmlLoss([], [element("p")]), null);
});
