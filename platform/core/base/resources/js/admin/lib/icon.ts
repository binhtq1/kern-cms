import sanitizeHtml from "sanitize-html";
export function cleanIcon(html: string): string {
    return sanitizeHtml(html, {
        allowedTags: [
            "svg",
            "g",
            "path",
            "circle",
            "rect",
            "line",
            "polyline",
            "polygon",
            "ellipse",
        ],
        allowedAttributes: {
            svg: [
                "viewBox",
                "width",
                "height",
                "fill",
                "stroke",
                "stroke-width",
                "stroke-linecap",
                "stroke-linejoin",
            ],
            "*": [
                "d",
                "x",
                "y",
                "x1",
                "x2",
                "y1",
                "y2",
                "cx",
                "cy",
                "r",
                "rx",
                "ry",
                "width",
                "height",
                "points",
                "fill",
                "stroke",
                "stroke-width",
                "stroke-linecap",
                "stroke-linejoin",
                "transform",
            ],
        },
        transformTags: {
            "*": (tagName: string, attributes: Record<string, string>) => ({
                tagName,
                attribs: Object.fromEntries(
                    Object.entries(attributes).filter(
                        ([name, value]) =>
                            !["fill", "stroke"].includes(name) ||
                            /^(none|currentColor|transparent|#[\da-f]{3,8}|[a-z]+|rgba?\([\d.,% ]+\))$/i.test(
                                value,
                            ),
                    ),
                ),
            }),
        },
        parser: { lowerCaseAttributeNames: false },
        allowedSchemes: [],
        allowProtocolRelative: false,
    });
}
