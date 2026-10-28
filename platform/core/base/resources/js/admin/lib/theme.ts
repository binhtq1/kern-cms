export type ThemeMode = "light" | "dark" | "system";
const storageKey = "kern-admin-theme";
let mode: ThemeMode = "system";
let initialized = false;
let explicitPreference = false;
let adoptedUserPreference = false;
const listeners = new Set<() => void>();

export function parseTheme(value: string | null): ThemeMode {
    return value === "light" || value === "dark" ? value : "system";
}
export function resolveTheme(
    preference: ThemeMode,
    systemDark: boolean,
): "light" | "dark" {
    return preference === "system"
        ? systemDark
            ? "dark"
            : "light"
        : preference;
}
function applyTheme() {
    const resolved = resolveTheme(
        mode,
        window.matchMedia("(prefers-color-scheme: dark)").matches,
    );
    document.documentElement.classList.toggle("dark", resolved === "dark");
    document.documentElement.dataset.theme = mode;
    listeners.forEach((listener) => listener());
}
export function initializeTheme() {
    if (initialized) {
        return;
    }
    initialized = true;
    try {
        const saved = localStorage.getItem(storageKey);
        explicitPreference = saved !== null;
        mode = parseTheme(saved);
    } catch {
        mode = "system";
    }
    applyTheme();
    window
        .matchMedia("(prefers-color-scheme: dark)")
        .addEventListener("change", () => {
            if (mode === "system") {
                applyTheme();
            }
        });
    window.addEventListener("storage", (event) => {
        if (event.key === storageKey || event.key === null) {
            explicitPreference = event.newValue !== null;
            mode = parseTheme(event.newValue);
            applyTheme();
        }
    });
}
export function setTheme(preference: ThemeMode) {
    explicitPreference = true;
    mode = preference;
    try {
        localStorage.setItem(storageKey, mode);
    } catch {
        /* Theme remains usable when browser storage is unavailable. */
    }
    applyTheme();
}

/** A saved browser choice, including System, takes priority over account defaults. */
export function initialThemePreference(
    saved: string | null,
    userTheme: string | null,
): ThemeMode {
    if (saved !== null) return parseTheme(saved);
    return userTheme === "light" || userTheme === "dark" ? userTheme : "system";
}

export function adoptUserThemePreference(userTheme?: string | null) {
    if (
        adoptedUserPreference ||
        (userTheme !== "light" && userTheme !== "dark")
    )
        return;
    adoptedUserPreference = true;
    if (explicitPreference) return;
    let saved: string | null = null;
    try {
        saved = localStorage.getItem(storageKey);
    } catch {
        /* The in-memory explicit choice still wins when storage is blocked. */
    }
    if (saved === null) setTheme(initialThemePreference(saved, userTheme));
}

export function getTheme() {
    return mode;
}
export function subscribeTheme(listener: () => void) {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}
