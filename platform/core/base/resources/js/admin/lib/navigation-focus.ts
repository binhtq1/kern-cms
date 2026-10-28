export type FocusVisit = {
    id?: string | number;
    url?: URL | string;
    method?: string;
    preserveState?: unknown;
    preserveScroll?: unknown;
    only?: unknown[];
    except?: unknown[];
    data?: unknown;
};

export function shouldFocusNavigation(
    visit: FocusVisit,
    from: string,
): boolean {
    if (
        !(visit.url instanceof URL) &&
        (typeof visit.url !== "string" || !visit.url.trim())
    )
        return false;
    if (
        visit.id === undefined ||
        visit.method?.toLowerCase() !== "get" ||
        visit.preserveState !== false ||
        visit.preserveScroll !== false ||
        visit.only?.length ||
        visit.except?.length
    )
        return false;
    if (
        visit.data &&
        (typeof visit.data !== "object" || Object.keys(visit.data).length)
    )
        return false;
    try {
        const source = new URL(from);
        const destination = new URL(String(visit.url), source);
        return (
            source.origin === destination.origin &&
            source.pathname !== destination.pathname &&
            !destination.hash
        );
    } catch {
        return false;
    }
}

export function focusPageHeading(
    doc: Document,
    sourceFocus: Element | null,
): boolean {
    if (doc.querySelector('[role="dialog"], [aria-modal="true"], dialog[open]'))
        return false;
    const active = doc.activeElement;
    if (
        active?.matches(
            'input, textarea, select, [contenteditable]:not([contenteditable="false"])',
        ) ||
        (active as HTMLElement | null)?.isContentEditable
    )
        return false;
    if (active && active !== doc.body && active !== sourceFocus) return false;
    const target =
        doc.querySelector<HTMLElement>('main h1, [role="main"] h1') ??
        doc.querySelector<HTMLElement>('main, [role="main"]');
    if (!target || !target.isConnected) return false;
    if (!target.hasAttribute("tabindex")) target.setAttribute("tabindex", "-1");
    target.classList.add(
        "focus-visible:outline-2",
        "focus-visible:outline-solid",
        "focus-visible:outline-ring",
        "focus-visible:outline-offset-4",
    );
    target.focus({ preventScroll: true });
    return true;
}

type EventName = "start" | "navigate" | "success" | "error" | "finish";
type FocusEvent = { detail: Record<string, unknown> };
export type FocusSubscribe = (
    name: EventName,
    callback: (event: FocusEvent) => void,
) => () => void;

export function installNavigationFocus(
    subscribe: FocusSubscribe,
    doc: Document = document,
    location: () => string = () => window.location.href,
    schedule: (callback: () => void) => number = requestAnimationFrame,
    cancel: (id: number) => void = cancelAnimationFrame,
): () => void {
    let generation = 0;
    let frame: number | undefined;
    let pending:
        | {
              id: string | number;
              source: Element | null;
              navigated: boolean;
              generation: number;
          }
        | undefined;
    const dispose = [
        subscribe("start", ({ detail }) => {
            generation++;
            if (frame !== undefined) cancel(frame);
            pending = undefined;
            const visit = detail.visit as FocusVisit | undefined;
            if (
                visit &&
                !doc.activeElement?.closest("form") &&
                shouldFocusNavigation(visit, location())
            )
                pending = {
                    id: visit.id!,
                    source: doc.activeElement,
                    navigated: false,
                    generation,
                };
        }),
        subscribe("navigate", ({ detail }) => {
            if (
                pending &&
                detail.type === "visit" &&
                detail.visitId === pending.id
            )
                pending.navigated = true;
            else if (detail.type === "history" || detail.type === "initial") {
                pending = undefined;
                generation++;
            }
        }),
        subscribe("success", ({ detail }) => {
            if (!pending?.navigated || detail.visitId !== pending.id) return;
            const visit = pending;
            pending = undefined;
            frame = schedule(() => {
                frame = undefined;
                if (generation === visit.generation)
                    focusPageHeading(doc, visit.source);
            });
        }),
        subscribe("error", () => {
            pending = undefined;
            generation++;
        }),
        subscribe("finish", ({ detail }) => {
            const visit = detail.visit as FocusVisit | undefined;
            if (pending && visit?.id === pending.id) pending = undefined;
        }),
    ];
    return () => {
        generation++;
        pending = undefined;
        if (frame !== undefined) cancel(frame);
        dispose.forEach((stop) => stop());
    };
}
