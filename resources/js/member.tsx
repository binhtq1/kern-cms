import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../vendor/binhtq1/kern-core/core/base/resources/css/admin.css";

const pages = import.meta.glob<{ default: ResolvedComponent }>(
    "../../platform/plugins/member/resources/js/account/{Dashboard,Settings,Posts,PostForm}.tsx",
);
void createInertiaApp({
    title: (title) => title,
    resolve: async (name) => {
        const path = `../../platform/plugins/member/resources/js/account/${name}.tsx`;
        const load = pages[path];
        if (!load) throw new Error("Unknown member page");
        return (await load()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: "#214f7d" },
}).catch(() => {
    const element = document.getElementById("app");
    if (!element) return;
    createRoot(element).render(
        <main className="mx-auto max-w-xl space-y-4 p-8">
            <h1 className="text-xl font-semibold">
                The account page could not load
            </h1>
            <p>
                Your saved information has not changed. Reload the page to try
                again.
            </p>
            <button
                className="rounded border px-4 py-2"
                onClick={() => location.reload()}
            >
                Reload
            </button>
        </main>,
    );
});
