import test from "node:test";
import assert from "node:assert/strict";
import { startPointerSession } from "./pointer-session.ts";

function gesture(start = {}) {
    const events = new EventTarget();
    const frames = new Map();
    const drops = [];
    const targets = [];
    const moves = [];
    const scrolls = [];
    let activations = 0;
    let stops = 0;
    let sequence = 0;
    let scrolled = false;
    const origin = {
        pointerId: 7,
        clientX: 100,
        clientY: 200,
        button: 0,
        isPrimary: true,
        ...start,
    };
    const dispose = startPointerSession(origin, {
        events,
        requestFrame: (callback) => {
            frames.set(++sequence, callback);
            return sequence;
        },
        cancelFrame: (frame) => frames.delete(frame),
        viewportHeight: () => 600,
        scrollBy: (delta) => {
            scrolls.push(delta);
            scrolled = true;
        },
        locate: (x, y) => {
            if (x < 0 || y < 0 || x > 800 || y > 600) return null;
            return scrolled ? "after-scroll" : x >= 400 ? "footer" : "sidebar";
        },
        onActivate: () => activations++,
        onMove: (x, y) => moves.push([x, y]),
        onTarget: (target) => targets.push(target),
        onDrop: (target) => drops.push(target),
        onStop: () => stops++,
    });
    return {
        drops,
        targets,
        moves,
        scrolls,
        frames,
        dispose,
        get activations() {
            return activations;
        },
        get stops() {
            return stops;
        },
        send(type, data = {}) {
            const event = new Event(type, { cancelable: true });
            Object.assign(event, origin, {
                buttons: type === "pointerup" ? 0 : 1,
                ...data,
            });
            events.dispatchEvent(event);
            return event;
        },
        tick() {
            const callbacks = [...frames.values()];
            frames.clear();
            callbacks.forEach((callback) => callback(16));
        },
    };
}

for (const pointerType of ["mouse", "touch", "pen"]) {
    test(`${pointerType} activates after movement and drops at the final release position`, () => {
        const drag = gesture({ pointerType });

        const smallMove = drag.send("pointermove", {
            clientX: 103,
            clientY: 204,
        });
        assert.equal(drag.activations, 0);
        assert.equal(smallMove.defaultPrevented, false);
        const activeMove = drag.send("pointermove", { clientX: 106 });
        assert.equal(activeMove.defaultPrevented, true);
        assert.equal(drag.activations, 1);
        assert.equal(drag.targets.at(-1), "sidebar");
        drag.send("pointerup", { clientX: 500 });

        assert.deepEqual(drag.drops, ["footer"]);
        assert.equal(drag.stops, 1);
        assert.equal(drag.frames.size, 0);
        drag.send("pointerup", { clientX: 700 });
        assert.deepEqual(drag.drops, ["footer"]);
    });
}

test("secondary pointers and non-left buttons do not begin sorting", () => {
    for (const origin of [{ button: 2 }, { isPrimary: false }]) {
        const drag = gesture(origin);

        drag.send("pointermove", { clientX: 500 });
        drag.send("pointerup", { clientX: 500 });
        drag.dispose();

        assert.equal(drag.activations, 0);
        assert.deepEqual(drag.drops, []);
        assert.equal(drag.frames.size, 0);
    }
});

test("other pointer IDs cannot move, cancel or release the active gesture", () => {
    const drag = gesture();

    drag.send("pointermove", { pointerId: 8, clientX: 500 });
    assert.equal(drag.activations, 0);
    drag.send("pointermove", { clientX: 110 });
    drag.send("pointermove", { pointerId: 8, clientX: 500, buttons: 0 });
    drag.send("pointercancel", { pointerId: 8 });
    drag.send("pointerup", { pointerId: 8, clientX: 500 });
    assert.equal(drag.stops, 0);
    assert.deepEqual(drag.moves, [[110, 200]]);
    drag.send("pointerup", { clientX: 120 });

    assert.deepEqual(drag.drops, ["sidebar"]);
});

for (const cancellation of ["Escape", "blur", "pointercancel", "dispose"]) {
    test(`${cancellation} cancels sorting, stops animation and removes listeners`, () => {
        const drag = gesture();
        drag.send("pointermove", { clientX: 120, clientY: 580 });
        drag.tick();
        assert.deepEqual(drag.scrolls, [11]);

        if (cancellation === "dispose") drag.dispose();
        else if (cancellation === "Escape") {
            assert.equal(
                drag.send("keydown", { key: "Escape" }).defaultPrevented,
                true,
            );
        } else drag.send(cancellation);
        drag.tick();
        drag.send("pointermove", { clientX: 500 });
        drag.send("pointerup", { clientX: 500 });
        drag.dispose();

        assert.equal(drag.stops, 1);
        assert.equal(drag.frames.size, 0);
        assert.deepEqual(drag.scrolls, [11]);
        assert.deepEqual(drag.drops, []);
        assert.deepEqual(drag.moves, [[120, 580]]);
    });
}

test("subthreshold releases and drops outside the viewport never change ordering", () => {
    const click = gesture();
    click.send("pointermove", { clientX: 104 });
    click.send("pointerup", { clientX: 500 });
    assert.deepEqual(click.drops, []);

    const drag = gesture();
    drag.send("pointermove", { clientX: 120 });
    assert.equal(drag.targets.at(-1), "sidebar");
    drag.send("pointerup", { clientX: -1 });
    assert.equal(drag.targets.at(-1), null);
    assert.deepEqual(drag.drops, []);
});

test("a missing pointerup cancels on the next move without the primary button", () => {
    const drag = gesture();
    drag.send("pointermove", { clientX: 120, clientY: 580 });

    drag.send("pointermove", { clientX: 500, buttons: 0 });
    drag.tick();
    drag.send("pointerup", { clientX: 500 });

    assert.deepEqual(drag.drops, []);
    assert.equal(drag.stops, 1);
    assert.deepEqual(drag.scrolls, []);
});

test("edge scrolling refreshes the hovered destination and stops after release", () => {
    const drag = gesture();
    drag.send("pointermove", { clientX: 120, clientY: 20 });
    assert.equal(drag.targets.at(-1), "sidebar");

    drag.tick();
    assert.deepEqual(drag.scrolls, [-11]);
    assert.equal(drag.targets.at(-1), "after-scroll");
    drag.send("pointerup", { clientX: 120, clientY: 20 });
    drag.tick();

    assert.deepEqual(drag.drops, ["after-scroll"]);
    assert.deepEqual(drag.scrolls, [-11]);
    assert.equal(drag.frames.size, 0);
});

test("unrelated keys preserve the drag until an explicit release", () => {
    const drag = gesture();
    drag.send("pointermove", { clientX: 120 });

    assert.equal(
        drag.send("keydown", { key: "ArrowDown" }).defaultPrevented,
        false,
    );
    assert.equal(drag.stops, 0);
    drag.send("pointerup", { clientX: 500 });

    assert.deepEqual(drag.drops, ["footer"]);
});
