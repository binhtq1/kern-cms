export type AdminAppearance = {
    logo: string | null;
    favicon: string | null;
    logoHeight: number;
    loginBackground: string | null;
    containerWidth: "container-xl" | "container-3xl" | "container-fluid" | null;
    showMenuItemIcon: boolean;
    direction: "ltr" | "rtl";
    themeMode: "light" | "dark" | null;
    colors: Partial<
        Record<
            "primary" | "secondary" | "heading" | "text" | "link" | "linkHover",
            string
        >
    >;
    font: { family: string; url: string | null } | null;
};

function colorLuminance(color: string): number | null {
    const hex = color.replace(/^#/, "");
    if (!/^(?:[a-f\d]{3}|[a-f\d]{6})$/i.test(hex)) return null;
    const expanded =
        hex.length === 3
            ? [...hex].map((value) => value + value).join("")
            : hex;
    const channels = [0, 2, 4].map((offset) => {
        const value = parseInt(expanded.slice(offset, offset + 2), 16) / 255;
        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });
    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
}

export function colorForeground(color: string): string {
    const luminance = colorLuminance(color);
    if (luminance === null) return "#ffffff";
    return (luminance + 0.05) / 0.05 > 1.05 / (luminance + 0.05)
        ? "#000000"
        : "#ffffff";
}

export function readablePrimaryColor(color: string, dark = false): string {
    const luminance = colorLuminance(color);
    const surfaces = dark
        ? ["#171a1f", "#20252c", "#2a3441"]
        : ["#ffffff", "#f6f7f9", "#e4eaf1"];
    if (
        luminance !== null &&
        surfaces.every((surface) => {
            const background = colorLuminance(surface)!;
            return (
                (Math.max(luminance, background) + 0.05) /
                    (Math.min(luminance, background) + 0.05) >=
                4.5
            );
        })
    ) {
        return color;
    }
    return dark ? "#95bce5" : "#214f7d";
}

export function appearanceVariables(
    appearance?: AdminAppearance,
    dark = false,
): Record<string, string> {
    const colors = appearance?.colors ?? {};
    const variables: Record<string, string> = {};
    if (colors.primary) {
        variables["--primary"] = colors.primary;
        variables["--primary-ink"] = readablePrimaryColor(colors.primary, dark);
        variables["--ring"] = variables["--primary-ink"];
        variables["--primary-foreground"] = colorForeground(colors.primary);
    }
    if (!dark && colors.secondary) {
        variables["--secondary"] = colors.secondary;
        variables["--secondary-foreground"] = colorForeground(colors.secondary);
    }
    if (!dark && colors.text) variables["--foreground"] = colors.text;
    if (!dark && colors.heading)
        variables["--admin-heading-color"] = colors.heading;
    if (!dark && colors.link) variables["--admin-link-color"] = colors.link;
    if (!dark && colors.linkHover)
        variables["--admin-link-hover-color"] = colors.linkHover;
    if (appearance?.font)
        variables["--admin-font-family"] =
            `"${appearance.font.family}", ui-sans-serif, system-ui, sans-serif`;
    return variables;
}
