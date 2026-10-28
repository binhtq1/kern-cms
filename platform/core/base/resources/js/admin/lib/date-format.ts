export function formatAdminDate(
    value: string | null | undefined,
    locale = "en",
    includeTime = false,
    timeZone = "UTC",
): string {
    if (!value) return "—";
    const wallClock =
        /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?$/.test(
            value,
        );
    const date = new Date(
        wallClock
            ? value.length === 10
                ? `${value}T00:00:00Z`
                : `${value.replace(" ", "T")}Z`
            : value,
    );
    if (
        Number.isNaN(date.getTime()) ||
        (wallClock && date.toISOString().slice(0, 10) !== value.slice(0, 10))
    )
        return value;
    let displayZone = wallClock ? "UTC" : timeZone;
    try {
        new Intl.DateTimeFormat("en", { timeZone: displayZone });
    } catch {
        displayZone = "UTC";
    }
    const options: Intl.DateTimeFormatOptions = {
        timeZone: displayZone,
        year: "numeric",
        month: "short",
        day: "numeric",
        ...(includeTime ? { hour: "2-digit", minute: "2-digit" } : {}),
    };
    try {
        return new Intl.DateTimeFormat(
            locale.replaceAll("_", "-"),
            options,
        ).format(date);
    } catch {
        return new Intl.DateTimeFormat("en", options).format(date);
    }
}
