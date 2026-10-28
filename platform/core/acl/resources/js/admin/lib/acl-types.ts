import type {
    RegisteredField,
    RegisteredFilter,
} from "@admin/components/registered-table-controls";
import type { TableVisibility } from "@admin/components/table-tools";
export type Row = {
    id: number;
    name: string;
    email?: string;
    username?: string;
    activated?: boolean;
    super_user?: boolean;
    created_at?: string;
    author?: string;
    author_url?: string | null;
    can_status?: boolean;
    can_change?: boolean;
    description?: string;
    roles?: string[];
    role_id?: string;
    can_assign_role?: boolean;
    users_count?: number;
    edit_url: string | null;
    delete_url: string | null;
};
export type Props = {
    roleOptions?: { value: string; label: string }[];
    assignRoleUrl?: string | null;
    tableButtons?: string[];
    tableVisibility?: TableVisibility;
    title: string;
    kind: string;
    search: string;
    items: {
        data: Row[];
        total: number;
        current_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    bulkFields: RegisteredField[];
    filters: {
        advanced?: RegisteredFilter[];
        status?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    canStatus?: boolean;
    urls: { index: string; create: string | null; bulk: string };
};
