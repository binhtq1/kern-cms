type JsonValue =
    | string
    | number
    | boolean
    | null
    | JsonValue[]
    | { [key: string]: JsonValue };
export function parseJsonImport(
    text: string,
    maxBytes: number,
    maxRows: number,
): JsonValue[] {
    if (new TextEncoder().encode(text).byteLength > maxBytes)
        throw new Error("File is too large");
    const data: unknown = JSON.parse(text);
    if (!Array.isArray(data) || data.length === 0 || data.length > maxRows)
        throw new Error("Invalid JSON data");
    return data;
}
