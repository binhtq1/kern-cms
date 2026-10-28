export type PageRegistration = {
    version: 1;
    namespaces: Record<string, string>;
};

const segmentPattern = /^[A-Za-z][A-Za-z0-9-]*$/;
const supportDirectories = new Set([
    "components",
    "_components",
    "hooks",
    "lib",
]);

/**
 * Registrations are package-local; only pages present in the build can resolve.
 * components, _components, hooks and lib are reserved support directories at any
 * depth: their files are excluded and namespace mappings into them are rejected.
 * Other extension directories and mixed-case page names remain supported.
 */
export function createPageRegistry(
    registrations: Record<string, PageRegistration>,
    files: readonly string[],
) {
    const pages: Record<string, string> = Object.create(null);
    const namespaces = new Set<string>();
    for (const [path, registration] of Object.entries(registrations).sort()) {
        if (
            !/^\.\.\/\.\.\/(?:platform\/(?:core|packages|plugins)|vendor\/binhtq1\/kern-core\/(?:core|packages))\/[a-z0-9-]+\/resources\/js\/admin\/registration\.json$/.test(
                path,
            ) ||
            registration.version !== 1 ||
            !registration.namespaces ||
            typeof registration.namespaces !== "object" ||
            Array.isArray(registration.namespaces)
        ) {
            throw new Error(`Invalid admin registration: ${path}`);
        }
        const root = path.slice(0, -"registration.json".length);
        for (const [namespace, directory] of Object.entries(
            registration.namespaces,
        )) {
            if (
                !/^[A-Z][A-Za-z0-9]*$/.test(namespace) ||
                typeof directory !== "string" ||
                (directory !== "" &&
                    !directory
                        .split("/")
                        .every(
                            (segment) =>
                                segmentPattern.test(segment) &&
                                !supportDirectories.has(segment),
                        ))
            ) {
                throw new Error(`Invalid admin namespace: ${namespace}`);
            }
            if (namespaces.has(namespace)) {
                throw new Error(`Duplicate admin namespace: ${namespace}`);
            }
            namespaces.add(namespace);
            const prefix = `${root}${directory ? `${directory}/` : ""}`;
            for (const file of files) {
                if (!file.startsWith(prefix) || !file.endsWith(".tsx")) {
                    continue;
                }
                // Match the resolver's exclusions, including root namespaces.
                if (
                    file
                        .slice(root.length)
                        .split("/")
                        .slice(0, -1)
                        .some((segment) => supportDirectories.has(segment))
                ) {
                    continue;
                }
                const relative = file.slice(prefix.length, -4);
                if (
                    !relative
                        .split("/")
                        .every((segment) => segmentPattern.test(segment))
                ) {
                    continue;
                }
                pages[`${namespace}/${relative}`] = file;
            }
        }
    }
    return pages;
}

export function registeredPagePath(
    registry: Record<string, string>,
    name: string,
) {
    if (!Object.hasOwn(registry, name)) {
        throw new Error(`Admin page not registered: ${name}`);
    }
    return registry[name];
}
