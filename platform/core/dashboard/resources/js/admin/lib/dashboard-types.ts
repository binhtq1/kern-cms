export type Widget = {
    id: string | number;
    name: string;
    title: string;
    url: string;
    type: string;
    visible: boolean;
    value?: number | string;
    icon?: string;
    color?: string;
    column?: string;
    ranges?: { key: string; label: string }[];
    settings?: Record<string, unknown>;
};
export type WidgetRecord = {
    index?: number;
    title: string;
    url?: string | null;
    target?: string;
    created_at?: string;
    user?: string;
    avatar?: string;
    action?: string;
    module?: string;
    reference_name?: string;
    ip_address?: string;
    relative_time?: string;
    status_code?: number;
};
export type WidgetData = {
    configuration?: { title: string; description: string; url: string | null };
    records?: WidgetRecord[];
    stats?: Record<string, string | number>;
    chart?: { axis: string; visitors: number; pageViews: number }[];
    countries?: [string, number][];
    link_key?: string;
    target?: string;
    columns?: { key: string; label: string }[];
    rows?: Record<string, string | number>[];
    pagination?: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    empty?: { title: string; description: string };
};
