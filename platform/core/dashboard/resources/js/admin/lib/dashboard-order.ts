import type { Widget } from "./dashboard-types";
export function reorderDashboardWidgets(
    display: Widget[],
    source: string,
    target: string,
    after?: boolean,
) {
    if (source === target) return null;
    const normal = display.filter((widget) => widget.type === "widget");
    const sourceIndex = normal.findIndex((widget) => widget.name === source),
        targetIndex = normal.findIndex((widget) => widget.name === target);
    if (sourceIndex < 0 || targetIndex < 0) return null;
    const insertion =
        after === undefined
            ? targetIndex
            : targetIndex +
              (after ? 1 : 0) -
              (sourceIndex < targetIndex ? 1 : 0);
    normal.splice(insertion, 0, normal.splice(sourceIndex, 1)[0]);
    return {
        display: [
            ...display.filter((widget) => widget.type !== "widget"),
            ...normal,
        ],
        names: normal.map((widget) => widget.name),
    };
}
