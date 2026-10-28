export function StartupFailure() {
    return (
        <main className="mx-auto max-w-xl space-y-4 p-8">
            <h1 className="text-2xl font-semibold">
                This screen could not be loaded
            </h1>
            <p role="alert">Reload when you are ready to try again.</p>
            <button
                type="button"
                className="rounded border p-3 focus-visible:outline-2 focus-visible:outline-ring"
                onClick={() => window.location.reload()}
            >
                Reload page
            </button>
        </main>
    );
}
