import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { createTableNavigation } from "@admin/lib/table-navigation";

/** Keep rows in place while a table GET is pending, without duplicate visits. */
export function useTableNavigation() {
    const [loading, setLoading] = useState(false);
    const [navigation] = useState(() =>
        createTableNavigation((...args) => router.get(...args), setLoading),
    );
    useEffect(() => () => navigation.cancel(), [navigation]);
    return { loading, get: navigation.get };
}
