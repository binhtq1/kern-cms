export function completedContactReply(
    flash: { success?: string; error?: string } | undefined,
    submitted: string,
    current: string,
): { message: string } | null {
    if (!flash?.success || flash.error) return null;

    return { message: current === submitted ? "" : current };
}
