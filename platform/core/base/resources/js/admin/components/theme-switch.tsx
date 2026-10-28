import { useSyncExternalStore } from "react";
import { Monitor, Moon, Sun } from "lucide-react";
import { Button } from "./ui/button";
import {
    getTheme,
    setTheme,
    subscribeTheme,
    type ThemeMode,
} from "@admin/lib/theme";
import { useAdminTranslation } from "@admin/lib/translate";

const modes = [
    { value: "light", label: "Light", icon: Sun },
    { value: "dark", label: "Dark", icon: Moon },
    { value: "system", label: "System", icon: Monitor },
] as const;
export function ThemeSwitch() {
    const t = useAdminTranslation();
    const mode = useSyncExternalStore(
        subscribeTheme,
        getTheme,
        () => "system" as ThemeMode,
    );
    const current = modes.find((item) => item.value === mode) ?? modes[2];
    const next = modes[(modes.indexOf(current) + 1) % modes.length];
    const Icon = current.icon;
    const label = t("Switch to :mode", { mode: t(next.label) });

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            title={label}
            onClick={() => setTheme(next.value)}
        >
            <Icon aria-hidden="true" />
        </Button>
    );
}
