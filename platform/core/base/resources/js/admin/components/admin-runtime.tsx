import { router } from "@inertiajs/react";
import { useEffect, type ReactNode } from "react";
import { installNavigationFocus } from "../lib/navigation-focus";
import { ClientErrorBoundary } from "./client-error-boundary";
export function AdminRuntime({ children }: { children: ReactNode }) {
    useEffect(
        () =>
            installNavigationFocus((name, callback) =>
                router.on(name, callback),
            ),
        [],
    );
    return <ClientErrorBoundary>{children}</ClientErrorBoundary>;
}
