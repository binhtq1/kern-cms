import type { AdminAppearance } from "@admin/lib/appearance";
import type { AdminSession } from "@admin/lib/admin-session";

export type Option = {
    value: string;
    label: string;
    parent_id?: string | null;
};
export type NavigationItem = {
    label: string;
    url?: string;
    inertia?: boolean;
    active?: boolean;
    count?: number | null;
    children?: NavigationItem[];
};
export type SharedProps = {
    admin: {
        name: string;
        timeZone?: string;
        appearance?: AdminAppearance;
        session?: AdminSession | null;
        capabilities?: { browseMedia: boolean; uploadMedia: boolean };
        user: { name: string; email: string } | null;
        navigation: NavigationItem[];
        header?: {
            website: string | null;
            notifications: {
                count: number;
                url: string;
                readAll: string;
                clearAll: string;
            };
            contacts: {
                url: string | null;
                inertia: boolean;
                items: {
                    id: number | string;
                    name: string;
                    email: string;
                    phone: string | null;
                    createdAt: string | null;
                    url: string;
                }[];
            } | null;
        } | null;
        urls: {
            dashboard: string;
            logout: string;
            search: string;
            profile?: string | null;
        };
    };
    flash: { success?: string; error?: string };
    errors: Record<string, string>;
    [key: string]: unknown;
};
