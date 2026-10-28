export function setupStepPayload(
    data: Record<string, unknown>,
    step: number,
    themeFields: { primary_color: boolean; primary_font: boolean },
): Record<string, unknown> {
    const keys =
        step === 2
            ? [
                  "site_title",
                  "logo",
                  "favicon",
                  "admin_logo",
                  "admin_favicon",
                  ...(themeFields.primary_color ? ["primary_color"] : []),
                  ...(themeFields.primary_font ? ["primary_font"] : []),
              ]
            : step === 3
              ? ["username", "email", "password", "password_confirmation"]
              : [];
    return Object.fromEntries(keys.map((key) => [key, data[key]]));
}
export function setupHasPendingChanges(
    submitted: Record<string, unknown>,
    current: Record<string, unknown>,
    step: number,
    themeFields: { primary_color: boolean; primary_font: boolean },
): boolean {
    return (
        JSON.stringify(submitted) !==
        JSON.stringify(setupStepPayload(current, step, themeFields))
    );
}

export function setupExitCookie(hostname: string, now = new Date()): string {
    const expires = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    return `get_started_cookie=1;expires=${expires.toUTCString()};domain=${hostname};path=/`;
}
