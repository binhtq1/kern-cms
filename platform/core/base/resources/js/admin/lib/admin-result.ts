export function assertAdminResult(
    data: { error?: boolean; message?: string },
    fallback: string,
): void {
    if (data.error) throw new Error(data.message || fallback);
}
