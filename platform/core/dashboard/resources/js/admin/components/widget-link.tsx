import type { ReactNode } from "react";
export function safeUrl(value?: string | null) {
    if (!value) return undefined;
    try {
        const url = new URL(value, window.location.origin);
        return ["http:", "https:"].includes(url.protocol)
            ? url.href
            : undefined;
    } catch {
        return undefined;
    }
}
export function WidgetLink({
    url,
    target,
    children,
}: {
    url?: string | null;
    target?: string;
    children: ReactNode;
}) {
    const href = safeUrl(url);
    return href ? (
        <a
            href={href}
            target={target === "_blank" ? "_blank" : undefined}
            rel={target === "_blank" ? "noopener noreferrer" : undefined}
            className="text-primary-ink hover:underline"
        >
            {children}
        </a>
    ) : (
        <>{children}</>
    );
}
