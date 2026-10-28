import { Head } from "@inertiajs/react";
import { useEffect } from "react";
import { subscribeTheme, adoptUserThemePreference } from "@admin/lib/theme";
import {
    appearanceVariables,
    type AdminAppearance,
} from "@admin/lib/appearance";

export function AppearanceBridge({
    appearance,
}: {
    appearance?: AdminAppearance;
}) {
    useEffect(() => {
        const root = document.documentElement;
        const previousDirection = root.getAttribute("dir");
        const variables = appearanceVariables(appearance);
        const previousValues = Object.fromEntries(
            Object.keys(variables).map((key) => [
                key,
                root.style.getPropertyValue(key),
            ]),
        );
        const apply = () => {
            const current = appearanceVariables(
                appearance,
                root.classList.contains("dark"),
            );
            for (const key of Object.keys(variables)) {
                if (current[key]) root.style.setProperty(key, current[key]);
                else root.style.removeProperty(key);
            }
        };
        adoptUserThemePreference(appearance?.themeMode);
        apply();
        const unsubscribe = subscribeTheme(apply);
        root.dir = appearance?.direction ?? "ltr";
        root.toggleAttribute(
            "data-admin-link-color",
            !!appearance?.colors.link,
        );
        root.toggleAttribute(
            "data-admin-link-hover-color",
            !!appearance?.colors.linkHover,
        );
        return () => {
            unsubscribe();
            for (const [key, value] of Object.entries(previousValues)) {
                if (value) root.style.setProperty(key, value);
                else root.style.removeProperty(key);
            }
            if (previousDirection === null) root.removeAttribute("dir");
            else root.setAttribute("dir", previousDirection);
            root.removeAttribute("data-admin-link-color");
            root.removeAttribute("data-admin-link-hover-color");
        };
    }, [appearance]);
    return (
        <Head>
            {appearance?.favicon && (
                <link
                    head-key="admin-favicon"
                    rel="icon"
                    href={appearance.favicon}
                />
            )}
            {appearance?.font?.url && (
                <link
                    head-key="admin-font"
                    rel="stylesheet"
                    href={appearance.font.url}
                />
            )}
        </Head>
    );
}
