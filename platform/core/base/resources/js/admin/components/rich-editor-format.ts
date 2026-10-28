/** Native color controls need hex even when parsed editor styles use CSS rgb(). */
export function editorColorHex(value: unknown, fallback: string): string {
    if (typeof value !== "string") {
        return fallback;
    }
    const color = value.trim().toLowerCase();
    if (/^#[\da-f]{6}$/.test(color)) {
        return color;
    }
    if (/^#[\da-f]{3}$/.test(color)) {
        return `#${color
            .slice(1)
            .split("")
            .map((digit) => digit + digit)
            .join("")}`;
    }
    const rgb = color.match(
        /^rgba?\(\s*([\d.]+%?)[,\s]+([\d.]+%?)[,\s]+([\d.]+%?)(?:\s*[,/]\s*[\d.]+%?)?\s*\)$/,
    );
    if (rgb) {
        return `#${rgb
            .slice(1, 4)
            .map((part) =>
                Math.round(
                    Math.min(
                        255,
                        Math.max(
                            0,
                            part.endsWith("%")
                                ? (parseFloat(part) * 255) / 100
                                : parseFloat(part),
                        ),
                    ),
                )
                    .toString(16)
                    .padStart(2, "0"),
            )
            .join("")}`;
    }
    const names: Record<string, string> = {
        black: "#000000",
        white: "#ffffff",
        red: "#ff0000",
        green: "#008000",
        blue: "#0000ff",
        yellow: "#ffff00",
        gray: "#808080",
        grey: "#808080",
        orange: "#ffa500",
        purple: "#800080",
    };
    return names[color] ?? fallback;
}
