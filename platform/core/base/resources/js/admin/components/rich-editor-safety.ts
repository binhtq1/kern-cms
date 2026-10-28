/** A conservative DOM comparison: visual mode is only offered when its schema retains the source. */
export type HtmlSnapshot = {
    tag: string;
    attributes: Record<string, string>;
    children: (HtmlSnapshot | string)[];
};

export function findHtmlLoss(
    original: (HtmlSnapshot | string)[],
    rendered: (HtmlSnapshot | string)[],
): string | null {
    if (original.length !== rendered.length) {
        return "HTML structure";
    }
    for (let index = 0; index < original.length; index++) {
        const before = original[index];
        const after = rendered[index];
        if (typeof before === "string" || typeof after === "string") {
            if (before !== after) {
                return "text or embedded content";
            }
            continue;
        }
        if (before.tag !== after.tag) {
            return `<${before.tag}>`;
        }
        for (const [name, value] of Object.entries(before.attributes)) {
            if (after.attributes[name] !== value) {
                return `<${before.tag}> ${name}`;
            }
        }
        // Editor-generated defaults are safe, but only these known defaults may be added.
        for (const [name, value] of Object.entries(after.attributes)) {
            if (name in before.attributes) {
                continue;
            }
            if (
                (before.tag === "a" && ["target", "rel"].includes(name)) ||
                (["td", "th"].includes(before.tag) &&
                    ["colspan", "rowspan"].includes(name) &&
                    value === "1") ||
                (before.tag === "table" &&
                    name === "style" &&
                    /^min-width:\s*\d+px;?$/.test(value))
            ) {
                continue;
            }
            return `<${before.tag}> ${name}`;
        }
        const issue = findHtmlLoss(before.children, after.children);
        if (issue) {
            return issue;
        }
    }
    return null;
}

export function normalizeHtmlWhitespace(
    nodes: (HtmlSnapshot | string)[],
    parentTag = "",
): (HtmlSnapshot | string)[] {
    if (["pre", "code"].includes(parentTag)) {
        return nodes;
    }

    // A captioned image is serialized without the formatting newlines that
    // authors commonly add between its <img> and <figcaption>. Those newlines
    // do not carry content, but treating them as text would unnecessarily
    // force the editor into source mode for an otherwise supported figure.
    if (parentTag === "figure") {
        return nodes.filter(
            (node) => typeof node !== "string" || node.trim() !== "",
        );
    }

    const isBlock = (node: HtmlSnapshot | string | undefined) =>
        typeof node !== "string" &&
        node !== undefined &&
        /^(p|h[1-6]|figure|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|blockquote|pre|div|hr)$/.test(
            node.tag,
        );
    return nodes.filter(
        (node, index) =>
            typeof node !== "string" ||
            node.trim() !== "" ||
            !(
                isBlock(nodes[index - 1]) ||
                isBlock(nodes[index + 1]) ||
                /^(table|thead|tbody|tfoot|tr)$/.test(parentTag)
            ),
    );
}

export function findVisualHtmlLoss(
    original: (HtmlSnapshot | string)[],
    rendered: (HtmlSnapshot | string)[],
): string | null {
    const normalizeLists = (
        nodes: (HtmlSnapshot | string)[],
    ): (HtmlSnapshot | string)[] =>
        nodes.map((node) => {
            if (typeof node === "string") {
                return node;
            }
            let children = normalizeLists(node.children);
            const first = children[0];
            // List items require a leading paragraph in ProseMirror's schema.
            if (
                node.tag === "li" &&
                typeof first !== "string" &&
                first?.tag === "p" &&
                !Object.keys(first.attributes).length &&
                first.children.length > 0 &&
                children
                    .slice(1)
                    .every(
                        (child) =>
                            typeof child !== "string" &&
                            /^(p|ul|ol|blockquote|pre|table|h[1-6]|hr)$/.test(
                                child.tag,
                            ),
                    )
            ) {
                children = [...first.children, ...children.slice(1)];
            }
            // ProseMirror requires a paragraph inside a blockquote. Raw HTML
            // permits its text directly, and both forms preserve the same
            // quote in the visual editor.
            if (
                node.tag === "blockquote" &&
                children.length === 1 &&
                typeof first !== "string" &&
                first?.tag === "p" &&
                !Object.keys(first.attributes).length
            ) {
                children = first.children;
            }
            return { ...node, children };
        });
    const before = normalizeLists(original);
    let after = normalizeLists(rendered);
    const lastBefore = before.at(-1);
    const lastAfter = after.at(-1);
    // StarterKit adds one empty cursor paragraph after a non-paragraph block.
    if (
        after.length === before.length + 1 &&
        typeof lastBefore !== "string" &&
        lastBefore !== undefined &&
        lastBefore.tag !== "p" &&
        typeof lastAfter !== "string" &&
        lastAfter?.tag === "p" &&
        !Object.keys(lastAfter.attributes).length &&
        lastAfter.children.length === 0
    ) {
        after = after.slice(0, -1);
    }
    return findHtmlLoss(before, after);
}

export function htmlSnapshot(html: string): (HtmlSnapshot | string)[] {
    const template = document.createElement("template");
    template.innerHTML = html;
    const read = (parent: ParentNode): (HtmlSnapshot | string)[] =>
        Array.from(parent.childNodes).flatMap(
            (node): (HtmlSnapshot | string)[] => {
                if (node.nodeType === Node.TEXT_NODE) {
                    return node.textContent ? [node.textContent] : [];
                }
                if (!(node instanceof HTMLElement)) {
                    return [
                        {
                            tag: "HTML comment or custom node",
                            attributes: {},
                            children: [],
                        },
                    ];
                }
                const tag =
                    { b: "strong", i: "em", strike: "s" }[
                        node.tagName.toLowerCase()
                    ] ?? node.tagName.toLowerCase();
                if (tag === "tbody" && node.attributes.length === 0) {
                    return read(node);
                }
                const attributes = Object.fromEntries(
                    Array.from(node.attributes).map(({ name, value }) => [
                        name,
                        name === "style"
                            ? Array.from(node.style)
                                  .sort()
                                  .map(
                                      (property) =>
                                          `${property}: ${node.style.getPropertyValue(property).trim()}${node.style.getPropertyPriority(property) ? " !important" : ""};`,
                                  )
                                  .join(" ")
                            : value,
                    ]),
                );
                let children = normalizeHtmlWhitespace(read(node), tag);
                if (
                    ["td", "th"].includes(tag) &&
                    children.length === 1 &&
                    typeof children[0] !== "string" &&
                    children[0].tag === "p" &&
                    !Object.keys(children[0].attributes).length
                ) {
                    children = children[0].children;
                }
                return [{ tag, attributes, children }];
            },
        );
    return normalizeHtmlWhitespace(read(template.content));
}

export function visualEditingIssue(
    source: string,
    rendered: string,
): string | null {
    if (!source.trim()) {
        return null;
    }
    const before = htmlSnapshot(source);
    const after = htmlSnapshot(rendered);
    if (
        before.every((node) => typeof node === "string") &&
        after.length === 1 &&
        typeof after[0] !== "string" &&
        after[0].tag === "p" &&
        !Object.keys(after[0].attributes).length &&
        after[0].children.every((node) => typeof node === "string") &&
        before.join("") === after[0].children.join("")
    ) {
        return null;
    }
    // Tiptap creates a column group from its column widths; retain source column groups strictly.
    const stripGeneratedColumns = (nodes: (HtmlSnapshot | string)[]) => {
        for (const node of nodes) {
            if (typeof node !== "string") {
                if (
                    node.tag === "table" &&
                    !source.toLowerCase().includes("<col")
                ) {
                    node.children = node.children.filter(
                        (child) =>
                            typeof child === "string" ||
                            child.tag !== "colgroup",
                    );
                }
                stripGeneratedColumns(node.children);
            }
        }
    };
    stripGeneratedColumns(after);
    return findVisualHtmlLoss(before, after);
}
