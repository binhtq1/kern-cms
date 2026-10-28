import { AdminRuntime } from "@admin/components/admin-runtime";
import { StartupFailure } from "@admin/components/startup-failure";
import { installAdminApi } from "@admin/lib/admin-api";
import {
    draftVisitOptions,
    initializeAdminSession,
    type AdminSession,
} from "@admin/lib/admin-session";
import {
    clientErrorReporter,
    installClientErrorReporting,
} from "@admin/lib/client-errors";
import { createBootstrapGuard } from "@admin/lib/bootstrap-guard";
import {
    createPageRegistry,
    registeredPagePath,
    type PageRegistration,
} from "@admin/lib/page-registry";
import { initializeTheme } from "@admin/lib/theme";
import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../vendor/binhtq1/kern-core/core/base/resources/css/admin.css";
initializeTheme();
const uninstallApi = installAdminApi();
const uninstallReporting = installClientErrorReporting();
import.meta.hot?.dispose(() => {
    uninstallApi();
    uninstallReporting();
});
const files = import.meta.glob<{ default: ResolvedComponent }>([
    "../../vendor/binhtq1/kern-core/core/*/resources/js/admin/**/*.tsx",
    "../../vendor/binhtq1/kern-core/packages/*/resources/js/admin/**/*.tsx",
    "../../platform/plugins/*/resources/js/admin/**/*.tsx",
    "!../../platform/*/*/resources/js/admin/**/{components,_components,hooks,lib}/**/*.tsx",
    "!../../vendor/binhtq1/kern-core/*/*/resources/js/admin/**/{components,_components,hooks,lib}/**/*.tsx",
]);
const registrations = import.meta.glob<PageRegistration>(
    [
        "../../vendor/binhtq1/kern-core/core/*/resources/js/admin/registration.json",
        "../../vendor/binhtq1/kern-core/packages/*/resources/js/admin/registration.json",
        "../../platform/plugins/*/resources/js/admin/registration.json",
    ],
    { eager: true, import: "default" },
);
const pages = createPageRegistry(registrations, Object.keys(files));
const bootstrap = createBootstrapGuard({
    report: (error) => clientErrorReporter.report(error),
    renderFailure: () => {
        const element = document.getElementById("app");
        if (element) createRoot(element).render(<StartupFailure />);
    },
});
void createInertiaApp({
    defaults: { visitOptions: draftVisitOptions },
    title: (title) => `${title} · Admin`,
    resolve: async (name) => {
        const resolve = files[registeredPagePath(pages, name)];
        return (await resolve()).default;
    },
    setup({ el, App, props }) {
        initializeAdminSession(
            (
                props.initialPage.props.admin as
                    { session?: AdminSession } | undefined
            )?.session,
        );
        const root = createRoot(el);
        bootstrap.didSetup();
        root.render(
            <AdminRuntime>
                <App {...props} />
            </AdminRuntime>,
        );
    },
    progress: { color: "#214f7d" },
}).catch(bootstrap.reject);
