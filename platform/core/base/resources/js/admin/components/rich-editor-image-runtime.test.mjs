import test from "node:test";
import assert from "node:assert/strict";
import { createServer } from "vite";
import { parseDocument } from "htmlparser2";
import { Document, Element, Text } from "domhandler";
import * as cssSelect from "css-select";
import * as serializer from "dom-serializer";
import { getSchema, commands } from "@tiptap/core";
import StarterKit from "@tiptap/starter-kit";
import { DOMParser, DOMSerializer } from "@tiptap/pm/model";
import { EditorState, NodeSelection } from "@tiptap/pm/state";
const render = serializer.default ?? serializer;
import { visualEditingIssue } from "./rich-editor-safety.ts";

// Adapt the installed HTML parser's DOM to ProseMirror's standard DOM boundary.
// The actual editor schema/parser/serializer and safety guard run unchanged.
function domDocument() {
    const wrap = (node) => {
        node.ownerDocument = document;
        Object.defineProperty(node, "nodeName", {
            get: () =>
                node instanceof Element
                    ? node.name.toUpperCase()
                    : node.type === "text"
                      ? "#text"
                      : "#document-fragment",
        });
        Object.defineProperty(node, "textContent", {
            get: () =>
                node.type === "text"
                    ? node.data
                    : (node.children ?? [])
                          .map((child) => child.textContent)
                          .join(""),
        });

        for (const child of node.children ?? []) wrap(child);
        node.appendChild = (child) => {
            child.parent = node;
            child.prev = node.children.at(-1) ?? null;
            if (child.prev) child.prev.next = child;
            node.children.push(child);
            return child;
        };
        if (node instanceof Element) {
            node.getAttribute = (name) => node.attribs[name] ?? null;
            node.setAttribute = (name, value) => {
                node.attribs[name] = String(value);
            };
            node.hasAttribute = (name) => Object.hasOwn(node.attribs, name);
            node.matches = (selector) => cssSelect.is(node, selector);
            node.querySelector = (selector) =>
                cssSelect.selectOne(selector, node.children);
            node.closest = (selector) => {
                for (
                    let current = node;
                    current instanceof Element;
                    current = current.parent
                )
                    if (current.matches(selector)) return current;
                return null;
            };
            const properties = () =>
                Object.fromEntries(
                    (node.attribs.style ?? "")
                        .split(";")
                        .filter((value) => value.includes(":"))
                        .map((value) => {
                            const index = value.indexOf(":");
                            return [
                                value.slice(0, index).trim(),
                                value.slice(index + 1).trim(),
                            ];
                        }),
                );
            node.style = {
                get cssText() {
                    return node.attribs.style ?? "";
                },
                set cssText(value) {
                    node.attribs.style = value;
                },
                get cssFloat() {
                    return properties().float ?? "";
                },
                getPropertyValue(name) {
                    return properties()[name] ?? "";
                },
                getPropertyPriority() {
                    return "";
                },
                [Symbol.iterator]() {
                    return Object.keys(properties())[Symbol.iterator]();
                },
            };
            if (node.name === "template")
                Object.defineProperty(node, "innerHTML", {
                    set(value) {
                        node.content = wrap(parseDocument(value));
                    },
                });
        }
        return node;
    };
    const document = {
        createElement: (name) => wrap(new Element(name, {}, [])),
        createTextNode: (value) => wrap(new Text(value)),
        createDocumentFragment: () => wrap(new Document([])),
        parse: (value) => wrap(parseDocument(value)),
    };
    return document;
}

test("legacy paragraph images preserve wrappers, text, links and block image/figure semantics through the installed editor schema", async () => {
    const server = await createServer({
        configFile: false,
        resolve: { dedupe: ["@tiptap/core", "@tiptap/pm"] },
        server: { middlewareMode: true, watch: null },
        appType: "custom",
        logLevel: "silent",
    });
    const previous = {
        document: globalThis.document,
        HTMLElement: globalThis.HTMLElement,
        Node: globalThis.Node,
    };
    try {
        const { CaptionedImage, InlineImage } = await server.ssrLoadModule(
            "/platform/core/base/resources/js/admin/components/rich-editor-extensions.ts",
        );
        const document = domDocument();
        globalThis.document = document;
        globalThis.HTMLElement = Element;
        globalThis.Node = { TEXT_NODE: 3 };
        assert.equal(
            visualEditingIssue(
                `<figure>
                    <img src="/storage/captioned.jpg" width="120">
                    <figcaption>Caption stays</figcaption>
                </figure>`,
                '<figure><img src="/storage/captioned.jpg" width="120"><figcaption>Caption stays</figcaption></figure>',
            ),
            null,
        );
        const schema = getSchema([StarterKit, CaptionedImage, InlineImage]);
        const parse = (html) =>
            DOMParser.fromSchema(schema).parse(document.parse(html));
        const serialize = (doc) =>
            render(
                DOMSerializer.fromSchema(schema).serializeFragment(
                    doc.content,
                    { document },
                ),
            );
        const plainTextQuote =
            "<p>Intro</p><blockquote>Quote stays</blockquote><p>Outro</p>";
        assert.equal(
            visualEditingIssue(
                plainTextQuote,
                serialize(parse(plainTextQuote)),
            ),
            null,
        );
        const cases = [
            ['<p><img src="/storage/sole.jpg" alt="Sole"></p>', "inlineImage"],
            [
                '<p>Before <img src="/storage/mixed.jpg" width="120" height="80"> after</p>',
                "inlineImage",
            ],
            [
                '<p>Before <a href="https://example.test/page" target="_blank" rel="noopener noreferrer"><img src="/storage/linked.jpg"></a> after</p>',
                "inlineImage",
            ],
            [
                '<p>Before <img src="/storage/floated.jpg" style="float: right; width: 120px; height: 80px;"> after</p>',
                "inlineImage",
            ],
            ['<img src="/storage/root.jpg" alt="Root">', "image"],
            [
                '<figure><img src="/storage/captioned.jpg" width="120"><figcaption>Caption stays</figcaption></figure>',
                "image",
            ],
            [
                '<figure><img src="http://localhost:8000/storage/magazine-demo/saigon-morning.png" alt="Một sáng chậm ở Sài Gòn, bắt đầu từ những con hẻm"><figcaption>Một sáng chậm ở Sài Gòn, bắt đầu từ những con hẻm</figcaption></figure>',
                "image",
            ],
        ];
        for (const [html, expected] of cases) {
            const doc = parse(html);
            const images = [];
            doc.descendants((node, pos) => {
                if (["image", "inlineImage"].includes(node.type.name))
                    images.push({ node, pos });
            });
            assert.equal(images.length, 1);
            assert.equal(images[0].node.type.name, expected);
            assert.equal(visualEditingIssue(html, serialize(doc)), null, html);
            const state = EditorState.create({
                schema,
                doc,
                selection: NodeSelection.create(doc, images[0].pos),
            });
            const transaction = state.tr;
            assert.equal(
                commands.updateAttributes(expected, {
                    alt: "Edited alternative",
                    width: "240",
                    height: "160",
                })({ state, tr: transaction, dispatch: () => {} }),
                true,
            );
            const changed = state.apply(transaction);
            assert.equal(changed.doc.textContent, doc.textContent);
            assert.equal(
                changed.doc.firstChild.type.name,
                doc.firstChild.type.name,
            );
            const reparsed = parse(serialize(changed.doc));
            assert.equal(
                reparsed.nodeAt(images[0].pos).attrs.alt,
                "Edited alternative",
            );
            assert.equal(
                String(reparsed.nodeAt(images[0].pos).attrs.width),
                "240",
            );
            assert.equal(reparsed.nodeAt(images[0].pos).type.name, expected);
            assert.equal(
                visualEditingIssue(serialize(changed.doc), serialize(reparsed)),
                null,
            );
        }
        const blockOnlySchema = getSchema([StarterKit, CaptionedImage]);
        const oldParagraphImage =
            '<p>Before <img src="/storage/legacy.jpg"> after</p>';
        const oldDoc = DOMParser.fromSchema(blockOnlySchema).parse(
            document.parse(oldParagraphImage),
        );
        assert.notEqual(
            visualEditingIssue(
                oldParagraphImage,
                render(
                    DOMSerializer.fromSchema(blockOnlySchema).serializeFragment(
                        oldDoc.content,
                        { document },
                    ),
                ),
            ),
            null,
        );
        const unsupported =
            '<p class="legacy-custom">Before <img src="/storage/preserved.jpg"> after</p>';
        assert.notEqual(
            visualEditingIssue(unsupported, serialize(parse(unsupported))),
            null,
        );
        assert.equal(schema.nodes.inlineImage.isInline, true);
        assert.equal(schema.nodes.image.isBlock, true);
    } finally {
        Object.assign(globalThis, previous);
        await server.close();
    }
});
