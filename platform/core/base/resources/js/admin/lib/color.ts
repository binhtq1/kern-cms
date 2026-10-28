export type Color = { r: number; g: number; b: number; a: number };
export function parseColor(value: string): Color | null {
    const text = value.trim().toLowerCase();
    if (text === "transparent") return { r: 0, g: 0, b: 0, a: 0 };
    const hex = /^#([\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})$/.exec(text)?.[1];
    if (hex) {
        const full = hex.length < 5 ? [...hex].map((c) => c + c).join("") : hex;
        return {
            r: parseInt(full.slice(0, 2), 16),
            g: parseInt(full.slice(2, 4), 16),
            b: parseInt(full.slice(4, 6), 16),
            a: full.length === 8 ? parseInt(full.slice(6, 8), 16) / 255 : 1,
        };
    }
    const rgb =
        /^rgba?\(\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)(?:\s*,\s*(\d*\.?\d+))?\s*\)$/.exec(
            text,
        );
    if (!rgb) return null;
    const [r, g, b] = rgb.slice(1, 4).map(Number);
    const a = rgb[4] === undefined ? 1 : Number(rgb[4]);
    return [r, g, b].every((n) => n >= 0 && n <= 255) && a >= 0 && a <= 1
        ? { r: Math.round(r), g: Math.round(g), b: Math.round(b), a }
        : null;
}
export function formatColor({ r, g, b, a }: Color): string {
    const hex = `#${[r, g, b].map((n) => Math.round(n).toString(16).padStart(2, "0")).join("")}`;
    return a === 1
        ? hex
        : `${hex}${Math.round(a * 255)
              .toString(16)
              .padStart(2, "0")}`;
}
export function normalizeColorValue(
    value: string,
    allowInherited = false,
): string | null {
    const trimmed = value.trim();
    if (!trimmed) return "";
    if (allowInherited && trimmed.toLowerCase() === "inherit") return "inherit";
    const parsed = parseColor(trimmed);
    return parsed ? formatColor(parsed) : null;
}

export const colorPalette = [
    "#000000",
    "#666666",
    "#b7b7b7",
    "#d9d9d9",
    "#efefef",
    "#ffffff",
    "#e6b8af",
    "#f4cccc",
    "#fce5cd",
    "#fff2cc",
    "#d9ead3",
    "#d0e0e3",
    "#c9daf8",
    "#cfe2f3",
    "#d9d2e9",
    "#ead1dc",
    "#cc4125",
    "#e06666",
    "#f6b26b",
    "#ffd966",
    "#93c47d",
    "#76a5af",
    "#6d9eeb",
    "#6fa8dc",
    "#8e7cc3",
    "#c27ba0",
    "#990000",
    "#b45f06",
    "#7f6000",
    "#274e13",
    "#134f5c",
    "#214f7d",
    "#351c75",
    "#741b47",
];
