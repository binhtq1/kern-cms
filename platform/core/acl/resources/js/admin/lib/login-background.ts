const resourcePrefix =
    "platform/core/acl/resources/js/admin/assets/backgrounds/";

export function resolveLoginBackground(
    value: string | null | undefined,
    assets: Record<string, unknown>,
): string | null {
    if (!value) return null;
    if (!value.startsWith(resourcePrefix)) return value;
    const asset = assets[`/${value}`];
    return typeof asset === "string" ? asset : null;
}
