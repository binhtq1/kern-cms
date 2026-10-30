import { parseDocument } from "htmlparser2";
import { Document, Element, Text } from "domhandler";
import * as cssSelect from "css-select";
import * as serializer from "dom-serializer";
const render = serializer.default ?? serializer;

// Adapt the installed HTML parser's DOM to ProseMirror's standard DOM boundary.
// The actual editor schema/parser/serializer and safety guard run unchanged.
export function domDocument() {
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
            node.style = new Proxy(
                {
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
                },
                {
                    get(target, name) {
                        if (name in target) return target[name];
                        return typeof name === "string"
                            ? (properties()[
                                  name.replace(
                                      /[A-Z]/g,
                                      (char) => `-${char.toLowerCase()}`,
                                  )
                              ] ?? "")
                            : undefined;
                    },
                },
            );
            Object.defineProperty(node, "innerHTML", {
                get() {
                    return render(node.children, { encodeEntities: "utf8" });
                },
                set(value) {
                    const parsed = wrap(parseDocument(value));
                    if (node.name === "template") node.content = parsed;
                    else {
                        node.children = parsed.children;
                        for (const child of node.children) child.parent = node;
                    }
                },
            });
            if (node.name === "textarea")
                Object.defineProperty(node, "value", {
                    get() {
                        return node.textContent;
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
