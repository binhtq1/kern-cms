import assert from "node:assert/strict";
import { test } from "node:test";

async function browserTheme({
    saved = null,
    dark = false,
    storageBlocked = false,
} = {}) {
    let stored = saved;
    let darkClass = false;
    const mediaListeners = new Set();
    const windowListeners = new Map();
    const media = {
        matches: dark,
        addEventListener: (_, callback) => mediaListeners.add(callback),
    };
    const root = {
        dataset: {},
        classList: {
            toggle: (_, enabled) => {
                darkClass = enabled;
            },
        },
    };
    const globals = {
        window: globalThis.window,
        document: globalThis.document,
        localStorage: globalThis.localStorage,
    };
    globalThis.window = {
        matchMedia: () => media,
        addEventListener: (name, callback) =>
            windowListeners.set(name, callback),
    };
    globalThis.document = { documentElement: root };
    globalThis.localStorage = {
        getItem: () => {
            if (storageBlocked) {
                throw new Error("Storage blocked");
            }
            return stored;
        },
        setItem: (_, value) => {
            if (storageBlocked) {
                throw new Error("Storage blocked");
            }
            stored = value;
        },
    };
    const theme = await import(`./theme.ts?test=${Math.random()}`);
    return {
        ...theme,
        root,
        get dark() {
            return darkClass;
        },
        get stored() {
            return stored;
        },
        systemChange(value) {
            media.matches = value;
            mediaListeners.forEach((listener) => listener());
        },
        storageChange(value, key = "kern-admin-theme") {
            windowListeners.get("storage")?.({ key, newValue: value });
        },
        cleanup() {
            Object.assign(globalThis, globals);
        },
    };
}

test("system follows OS changes while an explicit choice overrides the OS", async () => {
    const theme = await browserTheme({ dark: true });
    try {
        theme.initializeTheme();
        assert.equal(theme.getTheme(), "system");
        assert.equal(theme.dark, true);
        theme.systemChange(false);
        assert.equal(theme.dark, false);
        theme.setTheme("dark");
        theme.systemChange(false);
        assert.equal(theme.dark, true);
        assert.equal(theme.stored, "dark");
        theme.setTheme("light");
        theme.systemChange(true);
        assert.equal(theme.dark, false);
        theme.setTheme("system");
        assert.equal(theme.dark, true);
        assert.equal(theme.root.dataset.theme, "system");
    } finally {
        theme.cleanup();
    }
});

test("saved choice initializes correctly and cross-tab changes notify subscribers", async () => {
    const theme = await browserTheme({ saved: "light", dark: true });
    try {
        theme.initializeTheme();
        assert.equal(theme.dark, false);
        let notifications = 0;
        const unsubscribe = theme.subscribeTheme(() => notifications++);
        theme.storageChange("dark");
        assert.equal(theme.getTheme(), "dark");
        assert.equal(theme.dark, true);
        theme.storageChange("light", "unrelated-key");
        assert.equal(notifications, 1);
        theme.storageChange(null, null);
        assert.equal(theme.getTheme(), "system");
        assert.equal(notifications, 2);
        unsubscribe();
        theme.setTheme("light");
        assert.equal(notifications, 2);
    } finally {
        theme.cleanup();
    }
});

test("invalid storage falls back to system and blocked storage does not break switching", async () => {
    const theme = await browserTheme({
        saved: "invalid",
        dark: true,
        storageBlocked: true,
    });
    try {
        assert.equal(theme.parseTheme("invalid"), "system");
        theme.initializeTheme();
        assert.equal(theme.dark, true);
        assert.doesNotThrow(() => theme.setTheme("light"));
        assert.equal(theme.getTheme(), "light");
        assert.equal(theme.dark, false);
    } finally {
        theme.cleanup();
    }
});

test("an account default applies once when the browser has no saved preference", async () => {
    const theme = await browserTheme();
    try {
        theme.initializeTheme();
        theme.adoptUserThemePreference(null);
        assert.equal(theme.getTheme(), "system");
        theme.adoptUserThemePreference("dark");
        assert.equal(theme.getTheme(), "dark");
        assert.equal(theme.dark, true);
        assert.equal(theme.stored, "dark");
        theme.setTheme("system");
        theme.adoptUserThemePreference("light");
        assert.equal(theme.getTheme(), "system");
    } finally {
        theme.cleanup();
    }
});

test("saved light, dark and system choices take priority over account defaults", async () => {
    for (const saved of ["light", "dark", "system"]) {
        const theme = await browserTheme({ saved, dark: true });
        try {
            theme.initializeTheme();
            theme.adoptUserThemePreference("dark");
            assert.equal(theme.getTheme(), saved);
            assert.equal(theme.stored, saved);
            assert.equal(theme.initialThemePreference(saved, "dark"), saved);
        } finally {
            theme.cleanup();
        }
    }
});

test("blocked storage cannot reset a header choice on later navigation", async () => {
    const theme = await browserTheme({ storageBlocked: true });
    try {
        theme.initializeTheme();
        theme.adoptUserThemePreference("dark");
        assert.equal(theme.getTheme(), "dark");
        theme.setTheme("light");
        theme.adoptUserThemePreference("dark");
        assert.equal(theme.getTheme(), "light");
        assert.equal(theme.dark, false);
    } finally {
        theme.cleanup();
    }
});

test("a header choice made before user defaults arrive is preserved without storage", async () => {
    const theme = await browserTheme({ storageBlocked: true });
    try {
        theme.initializeTheme();
        theme.setTheme("system");
        theme.adoptUserThemePreference("dark");
        assert.equal(theme.getTheme(), "system");
        assert.equal(theme.initialThemePreference(null, "invalid"), "system");
        assert.equal(theme.initialThemePreference(null, "light"), "light");
    } finally {
        theme.cleanup();
    }
});
