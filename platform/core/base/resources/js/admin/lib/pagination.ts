export function pageSizeOptions(current: number): number[] {
    return [
        ...new Set([
            10,
            20,
            50,
            100,
            ...(Number.isInteger(current) && current >= 1 && current <= 100
                ? [current]
                : []),
        ]),
    ].sort((left, right) => left - right);
}
