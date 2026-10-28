export function createRoleRequests() {
    const requests = new Map<number, AbortController>();
    return {
        has: (id: number) => requests.has(id),
        begin(id: number) {
            const controller = new AbortController();
            requests.get(id)?.abort();
            requests.set(id, controller);
            return controller;
        },
        owns: (id: number, controller: AbortController) =>
            requests.get(id) === controller && !controller.signal.aborted,
        finish(id: number, controller: AbortController) {
            if (requests.get(id) === controller) requests.delete(id);
        },
        cancel() {
            for (const controller of requests.values()) controller.abort();
            requests.clear();
        },
    };
}
