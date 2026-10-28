import { Component, type ReactNode } from "react";
import {
    clientErrorReporter,
    type ClientErrorSource,
} from "../lib/client-errors";

export class ClientErrorBoundary extends Component<
    {
        children: ReactNode;
        source?: ClientErrorSource;
        fallback?: (retry: () => void) => ReactNode;
    },
    { failed: boolean }
> {
    state = { failed: false };
    static getDerivedStateFromError() {
        return { failed: true };
    }
    componentDidCatch(error: unknown) {
        clientErrorReporter.report(error, this.props.source ?? "boundary");
    }
    retry = () => this.setState({ failed: false });
    render() {
        if (!this.state.failed) return this.props.children;
        if (this.props.fallback) return this.props.fallback(this.retry);
        return (
            <main className="mx-auto max-w-xl space-y-4 p-8">
                <h1 className="text-2xl font-semibold">
                    This screen could not be displayed
                </h1>
                <p role="alert">
                    Reload when you are ready. Reloading may discard unsaved
                    changes.
                </p>
                <button
                    type="button"
                    className="ms-3 rounded border p-3 focus-visible:outline-2 focus-visible:outline-ring"
                    onClick={() => window.location.reload()}
                >
                    Reload page
                </button>
            </main>
        );
    }
}
