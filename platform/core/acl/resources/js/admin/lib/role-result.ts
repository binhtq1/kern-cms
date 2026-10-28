export function assertRoleAssignment(
    data: {
        error?: unknown;
        message?: string;
        component?: string;
        props?: {
            errors?: Record<string, string | string[]>;
            flash?: { error?: string };
            kind?: string;
        };
    },
    fallback: string,
) {
    const errors = data.props?.errors ?? {};
    if (data.error || data.props?.flash?.error || Object.keys(errors).length)
        throw new Error(
            Object.values(errors).flat().join(" ") ||
                data.props?.flash?.error ||
                data.message,
        );
    if (
        data.error !== false &&
        !(data.component === "ACL/Index" && data.props?.kind === "users")
    )
        throw new Error(fallback);
}
