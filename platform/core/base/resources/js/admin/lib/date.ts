import { usePage } from "@inertiajs/react";

import { formatAdminDate } from "./date-format";
export { formatAdminDate } from "./date-format";

export function useAdminDate() {
    const { admin } = usePage<{
        admin?: { locale?: string; timeZone?: string };
        [key: string]: unknown;
    }>().props;
    return (value: string | null | undefined, includeTime = false) =>
        formatAdminDate(value, admin?.locale, includeTime, admin?.timeZone);
}
