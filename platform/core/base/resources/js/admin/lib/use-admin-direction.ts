import { usePage } from "@inertiajs/react";
import type { SharedProps } from "@admin/types";

/** Portalled Radix controls need an explicit direction as well as the document dir. */
export function useAdminDirection(): "ltr" | "rtl" {
    return usePage<SharedProps>().props.admin?.appearance?.direction ?? "ltr";
}
