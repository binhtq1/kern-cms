import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@admin/components/ui/table";
import worldSource from "../lib/world-map.json?raw";
import { useAdminTranslation } from "@admin/lib/translate";

/*!
 * Geometry extracted from the bundled jVectorMap world_mill_en dataset.
 * Bundle attribution: jVectorMap 1.2.2, Copyright 2011-2013, Kirill Lebedev,
 * licensed under MIT. The geometry source carried no separate license header.
 * Only data is retained; no jQuery runtime is loaded.
 */
const world = JSON.parse(worldSource) as {
    width: number;
    height: number;
    paths: Record<string, { path: string; name: string }>;
};
export function AnalyticsVisuals({
    chart,
    countries,
}: {
    chart?: { axis: string; visitors: number; pageViews: number }[];
    countries?: [string, number][];
}) {
    const t = useAdminTranslation();
    const countryName = (code: string) => {
        try {
            return (
                new Intl.DisplayNames([document.documentElement.lang || "en"], {
                    type: "region",
                }).of(code.toUpperCase()) || code
            );
        } catch {
            return (
                world.paths[code.toUpperCase()]?.name || code || t("Unknown")
            );
        }
    };
    const maximum = Math.max(
        1,
        ...(chart?.flatMap((point) => [point.visitors, point.pageViews]) ?? []),
    );
    const points = (metric: "visitors" | "pageViews") =>
        chart
            ?.map(
                (point, index) =>
                    `${40 + (index / Math.max(1, chart.length - 1)) * 520},${220 - (point[metric] / maximum) * 190}`,
            )
            .join(" ") ?? "";
    const sessions = Object.fromEntries(
        countries?.map(([code, count]) => [
            code.toUpperCase(),
            Number(count) || 0,
        ]) ?? [],
    );
    const maximumSessions = Math.max(1, ...Object.values(sessions));
    return (
        <div className="grid gap-6 lg:grid-cols-2">
            {chart && chart.length === 0 && (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    {t("No results found")}
                </p>
            )}
            {!!chart?.length && (
                <div className="min-w-0">
                    <svg
                        viewBox="0 0 600 260"
                        role="img"
                        aria-label={t("Visitors and page views")}
                        className="w-full text-primary"
                    >
                        {[0, 1, 2, 3, 4].map((step) => (
                            <g key={step}>
                                <line
                                    x1="40"
                                    x2="560"
                                    y1={220 - step * 47.5}
                                    y2={220 - step * 47.5}
                                    stroke="currentColor"
                                    opacity=".15"
                                />
                                <text
                                    x="34"
                                    y={224 - step * 47.5}
                                    textAnchor="end"
                                    fontSize="10"
                                    fill="currentColor"
                                >
                                    {Math.round((maximum * step) / 4)}
                                </text>
                            </g>
                        ))}
                        <polygon
                            points={`40,220 ${points("visitors")} ${chart.length > 1 ? 560 : 40},220`}
                            fill="currentColor"
                            opacity=".08"
                        />
                        <polyline
                            points={points("visitors")}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                        />
                        <polyline
                            points={points("pageViews")}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeDasharray="5 3"
                            opacity=".6"
                        />
                        {chart.map((point, index) => (
                            <g key={index}>
                                <circle
                                    cx={
                                        40 +
                                        (index /
                                            Math.max(1, chart.length - 1)) *
                                            520
                                    }
                                    cy={220 - (point.visitors / maximum) * 190}
                                    r="3"
                                    fill="currentColor"
                                >
                                    <title>{`${point.axis}: ${t("Visitors")} ${point.visitors}, ${t("Page views")} ${point.pageViews}`}</title>
                                </circle>
                                {(index === 0 ||
                                    index === chart.length - 1 ||
                                    index %
                                        Math.max(
                                            1,
                                            Math.ceil(chart.length / 5),
                                        ) ===
                                        0) && (
                                    <text
                                        x={
                                            40 +
                                            (index /
                                                Math.max(1, chart.length - 1)) *
                                                520
                                        }
                                        y="245"
                                        textAnchor="middle"
                                        fontSize="10"
                                        fill="currentColor"
                                    >
                                        {point.axis}
                                    </text>
                                )}
                            </g>
                        ))}
                    </svg>
                    <p className="flex justify-center gap-5 text-xs text-muted-foreground">
                        <span>━ {t("Visitors")}</span>
                        <span>┄ {t("Page views")}</span>
                    </p>
                    <details className="mt-3 text-xs">
                        <summary className="cursor-pointer text-muted-foreground">
                            {t("View data")}
                        </summary>
                        <Table className="mt-2 w-full text-left">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t("Date")}</TableHead>
                                    <TableHead>{t("Visitors")}</TableHead>
                                    <TableHead>{t("Page views")}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {chart.map((point, index) => (
                                    <TableRow key={index}>
                                        <TableCell>{point.axis}</TableCell>
                                        <TableCell>{point.visitors}</TableCell>
                                        <TableCell>{point.pageViews}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </details>
                </div>
            )}
            {countries && (
                <div className="min-w-0">
                    <svg
                        viewBox={`0 0 ${world.width} ${world.height}`}
                        role="img"
                        aria-label={t("Sessions by country")}
                        className="w-full text-primary"
                    >
                        {Object.entries(world.paths).map(([code, country]) => (
                            <path
                                key={code}
                                d={country.path}
                                fill="currentColor"
                                fillOpacity={
                                    sessions[code]
                                        ? 0.3 +
                                          (0.7 * sessions[code]) /
                                              maximumSessions
                                        : 0.1
                                }
                                stroke="var(--card)"
                                strokeWidth=".5"
                            >
                                <title>{`${countryName(code)}: ${sessions[code] ?? 0} ${t("Sessions")}`}</title>
                            </path>
                        ))}
                    </svg>
                    <p className="mt-2 text-center text-xs text-muted-foreground">
                        {t("Darker areas indicate more sessions")}
                    </p>
                    {!countries.length && (
                        <p className="mt-3 text-center text-sm text-muted-foreground">
                            {t("No results found")}
                        </p>
                    )}
                    <details className="mt-3 text-xs">
                        <summary className="cursor-pointer text-muted-foreground">
                            {t("Sessions by country")}
                        </summary>
                        <Table className="mt-2 w-full text-left">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t("Country")}</TableHead>
                                    <TableHead className="text-right">
                                        {t("Sessions")}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {countries.map(([country, count]) => (
                                    <TableRow key={country}>
                                        <TableCell>
                                            {countryName(country)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {(
                                                Number(count) || 0
                                            ).toLocaleString()}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </details>
                </div>
            )}
        </div>
    );
}
