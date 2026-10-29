export async function copyToClipboard(text: string): Promise<void> {
    if (typeof globalThis.navigator?.clipboard?.writeText === "function") {
        try {
            await navigator.clipboard.writeText(text);
            return;
        } catch {
            // The user-triggered selection fallback also works on HTTP origins.
        }
    }

    const document = globalThis.document;
    if (!document?.body || typeof document.execCommand !== "function")
        throw new Error("Clipboard access is unavailable.");

    const focused = document.activeElement as HTMLElement | null;
    const selection = document.getSelection();
    const ranges = selection
        ? Array.from({ length: selection.rangeCount }, (_, index) =>
              selection.getRangeAt(index).cloneRange(),
          )
        : [];
    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.readOnly = true;
    textarea.tabIndex = -1;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    textarea.style.pointerEvents = "none";
    document.body.appendChild(textarea);
    try {
        textarea.focus({ preventScroll: true });
        textarea.select();
        if (!document.execCommand("copy"))
            throw new Error("Clipboard copy was denied.");
    } finally {
        textarea.remove();
        if (focused?.isConnected) focused.focus({ preventScroll: true });
        if (selection) {
            selection.removeAllRanges();
            for (const range of ranges) selection.addRange(range);
        }
    }
}
