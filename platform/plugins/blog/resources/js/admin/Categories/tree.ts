export type TreeNode = { id: string; parent: string; name: string };

export function descendants<T extends TreeNode>(
    nodes: T[],
    id: string,
): Set<string> {
    const ids = new Set([id]);
    for (let pass = 0; pass < nodes.length; pass++) {
        let changed = false;
        for (const node of nodes)
            if (ids.has(node.parent) && !ids.has(node.id)) {
                ids.add(node.id);
                changed = true;
            }
        if (!changed) break;
    }
    return ids;
}

export function serializeTree<T extends TreeNode>(
    nodes: T[],
): { id: string; name: string; children: object[] }[] {
    const visited = new Set<string>();
    const visit = (
        parent: string,
        depth: number,
    ): { id: string; name: string; children: object[] }[] =>
        nodes
            .filter((node) => node.parent === parent)
            .map((node) => {
                if (depth > 5 || visited.has(node.id))
                    throw new Error(
                        "Category depth cannot exceed five levels.",
                    );
                visited.add(node.id);
                return {
                    id: node.id,
                    name: node.name,
                    children: visit(node.id, depth + 1),
                };
            });
    const tree = visit("0", 1);
    if (visited.size !== nodes.length)
        throw new Error(
            "The category tree contains a cycle or missing parent.",
        );
    return tree;
}

export function moveNode<T extends TreeNode>(
    nodes: T[],
    id: string,
    parent: string,
    before?: string,
): T[] {
    const node = nodes.find((node) => node.id === id);
    if (!node || (parent !== "0" && !nodes.some((node) => node.id === parent)))
        return nodes;
    if (before === id && parent === node.parent) return nodes;
    if (descendants(nodes, id).has(parent))
        throw new Error(
            "A category cannot be moved into itself or its descendants.",
        );
    const next = nodes.filter((node) => node.id !== id);
    const index = before
        ? next.findIndex((node) => node.parent === parent && node.id === before)
        : -1;
    next.splice(index < 0 ? next.length : index, 0, { ...node, parent });
    serializeTree(next);
    return next;
}
