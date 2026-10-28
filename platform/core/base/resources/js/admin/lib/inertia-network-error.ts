type NetworkErrorEvent = {
    defaultPrevented: boolean;
    detail: { error: Error };
    preventDefault: () => void;
};

export function handleInertiaNetworkError(
    event: NetworkErrorEvent,
    showError: () => void,
): boolean {
    const error = event.detail.error;

    if (
        event.defaultPrevented ||
        !(error instanceof Error) ||
        error.name !== "HttpNetworkError" ||
        !("code" in error) ||
        error.code !== "ERR_NETWORK"
    ) {
        return false;
    }
    showError();
    event.preventDefault();
    return true;
}
