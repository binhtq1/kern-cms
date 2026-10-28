type PointerStart = Pick<
    PointerEvent,
    "pointerId" | "clientX" | "clientY" | "button" | "isPrimary"
>;

type PointerSession = {
    events: EventTarget;
    requestFrame: (callback: FrameRequestCallback) => number;
    cancelFrame: (frame: number) => void;
    viewportHeight: () => number;
    scrollBy: (delta: number) => void;
    locate: (x: number, y: number) => string | null;
    onActivate: (x: number, y: number) => void;
    onMove: (x: number, y: number) => void;
    onTarget: (target: string | null) => void;
    onDrop: (target: string) => void;
    onStop: () => void;
};

/** Owns one pointer gesture; disposal cancels rather than committing a drop. */
export function startPointerSession(
    origin: PointerStart,
    options: PointerSession,
): () => void {
    if (origin.button !== 0 || origin.isPrimary === false) return () => {};
    let current = { x: origin.clientX, y: origin.clientY };
    let activated = false;
    let stopped = false;
    let destination: string | null = null;
    let frame = 0;

    function locate() {
        destination = options.locate(current.x, current.y);
        options.onTarget(destination);
    }

    function scroll() {
        if (stopped || !activated) return;
        const edge = 64;
        const delta =
            current.y < edge
                ? -Math.ceil((edge - current.y) / 4)
                : current.y > options.viewportHeight() - edge
                  ? Math.ceil((current.y - options.viewportHeight() + edge) / 4)
                  : 0;
        if (delta) {
            options.scrollBy(delta);
            locate();
        }
        frame = options.requestFrame(scroll);
    }

    function move(raw: Event) {
        const event = raw as PointerEvent;
        if (stopped || event.pointerId !== origin.pointerId) return;
        // A release outside the window may not dispatch pointerup here.
        if ((event.buttons & 1) === 0) {
            stop();
            return;
        }
        current = { x: event.clientX, y: event.clientY };
        if (
            !activated &&
            Math.hypot(current.x - origin.clientX, current.y - origin.clientY) <
                6
        )
            return;
        if (!activated) {
            activated = true;
            options.onActivate(current.x, current.y);
            frame = options.requestFrame(scroll);
        }
        event.preventDefault();
        options.onMove(current.x, current.y);
        locate();
    }

    function finish(raw: Event) {
        const event = raw as PointerEvent;
        if (stopped || event.pointerId !== origin.pointerId) return;
        if (event.type === "pointerup" && activated) {
            current = { x: event.clientX, y: event.clientY };
            locate();
        }
        const drop =
            event.type === "pointerup" && activated ? destination : null;
        stop();
        if (drop) options.onDrop(drop);
    }

    function cancel(raw: Event) {
        if ((raw as KeyboardEvent).key === "Escape") {
            raw.preventDefault();
            stop();
        }
    }

    function stop() {
        if (stopped) return;
        stopped = true;
        options.cancelFrame(frame);
        options.events.removeEventListener("pointermove", move);
        options.events.removeEventListener("pointerup", finish);
        options.events.removeEventListener("pointercancel", finish);
        options.events.removeEventListener("keydown", cancel);
        options.events.removeEventListener("blur", stop);
        options.onStop();
    }

    options.events.addEventListener("pointermove", move, { passive: false });
    options.events.addEventListener("pointerup", finish);
    options.events.addEventListener("pointercancel", finish);
    options.events.addEventListener("keydown", cancel);
    options.events.addEventListener("blur", stop);
    return stop;
}
