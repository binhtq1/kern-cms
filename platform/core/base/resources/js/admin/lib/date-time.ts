export function calendarDays(year: number, month: number): (string | null)[] {
    const first = new Date(year, month, 1);
    const count = new Date(year, month + 1, 0).getDate();
    const prefix = Array.from({ length: first.getDay() }, () => null);
    return [
        ...prefix,
        ...Array.from(
            { length: count },
            (_, index) =>
                `${year.toString().padStart(4, "0")}-${String(month + 1).padStart(2, "0")}-${String(index + 1).padStart(2, "0")}`,
        ),
    ];
}
export function withinBounds(
    value: string,
    min?: string | number,
    max?: string | number,
): boolean {
    return (!min || value >= String(min)) && (!max || value <= String(max));
}
export function localDateValue(date: Date): string {
    return `${date.getFullYear().toString().padStart(4, "0")}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

export function timeFromDraft(value: string, type?: string): string {
    return (type === "time" ? value : value.split("T")[1]) || "00:00";
}
