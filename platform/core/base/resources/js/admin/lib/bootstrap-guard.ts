export function createBootstrapGuard({
    report,
    renderFailure,
}: {
    report: (error: unknown) => void;
    renderFailure: () => void;
}) {
    let setup = false;
    let failed = false;
    return {
        didSetup() {
            setup = true;
        },
        reject(error: unknown) {
            if (failed) return;
            failed = true;
            try {
                report(error);
            } catch {
                /* Reporting must not prevent recovery. */
            }
            if (!setup) renderFailure();
        },
    };
}
