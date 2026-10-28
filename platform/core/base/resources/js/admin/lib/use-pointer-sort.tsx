import { sortDestination } from "./sort";
import { startPointerSession } from "./pointer-session";
import { useEffect, useRef, useState, type PointerEvent } from "react";
import { createPortal } from "react-dom";
import { GripVertical } from "lucide-react";

type Drag = { key: string; label: string; x: number; y: number };

/** Pointer sorting works with mouse, pen and touch without native HTML drag images. */
export function usePointerSort(onDrop: (key: string, target: string) => void) {
    const [drag, setDrag] = useState<Drag | null>(null);
    const [target, setTarget] = useState<string | null>(null);
    const callback = useRef(onDrop);
    callback.current = onDrop;
    const cleanup = useRef<(() => void) | null>(null);
    const overlayRef = useRef<HTMLDivElement | null>(null);
    const position = (x: number, y: number) =>
        `translate3d(${Math.max(8, Math.min(x + 16, innerWidth - 240))}px, ${Math.max(8, Math.min(y + 12, innerHeight - 64))}px, 0)`;
    useEffect(() => () => cleanup.current?.(), []);
    function start(event: PointerEvent, key: string, label: string) {
        if (event.button !== 0 || event.isPrimary === false) return;
        event.preventDefault();
        event.stopPropagation();
        cleanup.current?.();
        const previousSelect = document.body.style.userSelect;
        const previousCursor = document.body.style.cursor;
        cleanup.current = startPointerSession(event, {
            events: window,
            requestFrame: (callback) => requestAnimationFrame(callback),
            cancelFrame: (frame) => cancelAnimationFrame(frame),
            viewportHeight: () => innerHeight,
            scrollBy: (delta) => window.scrollBy(0, delta),
            locate: (x, y) => {
                const element = document.elementFromPoint(x, y);
                const row = element?.closest<HTMLElement>("[data-sort-before]");
                if (row) {
                    return sortDestination(
                        row.getBoundingClientRect(),
                        x,
                        y,
                        row.dataset.sortBefore!,
                        row.dataset.sortAfter!,
                        row.dataset.sortInside,
                    );
                }
                return (
                    element?.closest<HTMLElement>("[data-sort-target]")?.dataset
                        .sortTarget ?? null
                );
            },
            onActivate: (x, y) => {
                setDrag({ key, label, x, y });
                document.body.style.userSelect = "none";
                document.body.style.cursor = "grabbing";
            },
            onMove: (x, y) => {
                if (overlayRef.current)
                    overlayRef.current.style.transform = position(x, y);
            },
            onTarget: setTarget,
            onDrop: (destination) => callback.current(key, destination),
            onStop: () => {
                document.body.style.userSelect = previousSelect;
                document.body.style.cursor = previousCursor;
                setDrag(null);
                setTarget(null);
                cleanup.current = null;
            },
        });
    }
    return {
        active: drag?.key ?? null,
        target,
        handle: (key: string, label: string) => ({
            onPointerDown: (event: PointerEvent) => start(event, key, label),
            onClick: (event: React.MouseEvent) => {
                event.preventDefault();
                event.stopPropagation();
            },
            style: { touchAction: "none" } as const,
        }),
        overlay: drag
            ? createPortal(
                  <div
                      ref={overlayRef}
                      aria-hidden="true"
                      className="pointer-events-none fixed z-[100] flex max-w-72 items-center gap-2 rounded-md border border-primary bg-card px-4 py-3 text-sm font-medium text-card-foreground shadow-lg"
                      style={{
                          left: 0,
                          top: 0,
                          transform: position(drag.x, drag.y),
                      }}
                  >
                      <GripVertical className="size-4 shrink-0 text-muted-foreground" />
                      {drag.label}
                  </div>,
                  document.body,
              )
            : null,
    };
}
