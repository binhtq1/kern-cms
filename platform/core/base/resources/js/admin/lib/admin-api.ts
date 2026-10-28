import axios, { type AxiosInstance } from "axios";
import {
    adminMutationUrl,
    adminSession,
    announceAdminResponse,
    currentCsrfHeaders,
    draftContextHeaders,
    withoutStaleCsrf,
} from "./admin-session.ts";

const installations = new WeakMap<AxiosInstance, () => void>();

export function installAdminApi(client: AxiosInstance = axios): () => void {
    const existing = installations.get(client);
    if (existing) return existing;
    const request = client.interceptors.request.use((config) => {
        const url = client.getUri(config);
        if (
            adminSession() &&
            config.method &&
            !["get", "head", "options"].includes(config.method.toLowerCase()) &&
            adminMutationUrl(url, window.location.origin)
        ) {
            const csrf = currentCsrfHeaders(undefined, undefined, url);
            if ("X-XSRF-TOKEN" in csrf) config.headers.delete("X-CSRF-TOKEN");
            config.headers.set({ ...csrf, ...draftContextHeaders() });
            config.data = withoutStaleCsrf(config.data);
        }
        return config;
    });
    const response = client.interceptors.response.use(
        (value) => value,
        (error: unknown) => {
            if (
                axios.isAxiosError(error) &&
                error.response &&
                adminMutationUrl(
                    client.getUri(error.config ?? {}),
                    window.location.origin,
                )
            ) {
                announceAdminResponse({
                    status: error.response.status,
                    headers: Object.fromEntries(
                        Object.entries(error.response.headers).map(
                            ([key, value]) => [key, String(value)],
                        ),
                    ),
                    data: error.response.data,
                });
            }
            return Promise.reject(error);
        },
    );
    const uninstall = () => {
        if (installations.get(client) !== uninstall) return;
        client.interceptors.request.eject(request);
        client.interceptors.response.eject(response);
        installations.delete(client);
    };
    installations.set(client, uninstall);
    return uninstall;
}

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}
const identifier = (value: unknown) =>
    typeof value === "string" ||
    (typeof value === "number" && Number.isFinite(value));
const namedItem = (value: unknown) =>
    isRecord(value) && identifier(value.id) && typeof value.name === "string";
const items = (value: unknown, check: (value: unknown) => boolean) =>
    Array.isArray(value) && value.every(check);
const positiveInteger = (value: unknown) =>
    Number.isInteger(value) && Number(value) >= 1;

export function requirePayload<T>(
    value: unknown,
    check: (value: unknown) => boolean,
): T {
    if (!check(value))
        throw new Error(
            "The server returned an invalid response. Please retry.",
        );
    return value as T;
}

export function adminResult<T = unknown>(
    value: unknown,
    check: (value: unknown) => boolean = () => true,
): { data: T; message: string } {
    if (!isRecord(value) || typeof value.error !== "boolean")
        throw new Error(
            "The server returned an invalid response. Please retry.",
        );
    if (value.error)
        throw new Error(
            typeof value.message === "string" && value.message
                ? value.message
                : "The request could not be completed.",
        );
    return {
        data: requirePayload<T>(value.data, check),
        message: typeof value.message === "string" ? value.message : "",
    };
}

export function isMediaList(value: unknown): boolean {
    const mediaItem = (item: unknown) =>
        namedItem(item) &&
        isRecord(item) &&
        (item.is_folder === undefined || typeof item.is_folder === "boolean") &&
        [
            "full_url",
            "indirect_url",
            "preview_url",
            "thumb",
            "size",
            "mime_type",
            "type",
            "preview_type",
            "alt",
            "color",
            "created_at",
            "updated_at",
        ].every(
            (key) =>
                item[key] === undefined ||
                item[key] === null ||
                typeof item[key] === "string",
        );
    return (
        isRecord(value) &&
        items(value.files, mediaItem) &&
        items(value.folders, mediaItem) &&
        items(value.breadcrumbs, namedItem)
    );
}
export function isMediaPicker(value: unknown): boolean {
    return (
        isRecord(value) &&
        items(
            value.data,
            (item) =>
                namedItem(item) &&
                isRecord(item) &&
                typeof item.id === "string" &&
                typeof item.url === "string" &&
                typeof item.src === "string" &&
                (item.mime_type === undefined ||
                    typeof item.mime_type === "string"),
        ) &&
        positiveInteger(value.current_page) &&
        positiveInteger(value.last_page) &&
        (value.folders === undefined || items(value.folders, namedItem)) &&
        (value.breadcrumbs === undefined || items(value.breadcrumbs, namedItem))
    );
}
export function isMenuReferences(value: unknown): boolean {
    return (
        isRecord(value) &&
        typeof value.type === "string" &&
        positiveInteger(value.current_page) &&
        positiveInteger(value.last_page) &&
        Number.isInteger(value.total) &&
        Number(value.total) >= 0 &&
        items(
            value.data,
            (item) =>
                isRecord(item) &&
                ["value", "label", "type", "id", "title", "url"].every(
                    (key) => typeof item[key] === "string",
                ),
        )
    );
}

export function assertAdminSave(value: unknown, component: string): void {
    if (!isRecord(value))
        throw new Error("The server returned an invalid save response.");
    const props =
        value.props === undefined
            ? {}
            : requirePayload<Record<string, unknown>>(value.props, isRecord);
    if (
        (props.errors !== undefined && !isRecord(props.errors)) ||
        (props.flash !== undefined && !isRecord(props.flash))
    )
        throw new Error("The server returned an invalid save response.");
    const errorValues = Object.values(props.errors ?? {});
    if (
        !errorValues.every(
            (message) =>
                typeof message === "string" ||
                (Array.isArray(message) &&
                    message.every((entry) => typeof entry === "string")),
        )
    )
        throw new Error("The server returned invalid validation errors.");
    const errors = errorValues
        .flat()
        .filter(
            (message): message is string =>
                typeof message === "string" && message.length > 0,
        );
    const flash = isRecord(props.flash) ? props.flash.error : null;
    if (flash !== null && flash !== undefined && typeof flash !== "string")
        throw new Error("The server returned an invalid save response.");
    const flashError = typeof flash === "string" ? flash : "";
    if (errorValues.length || flashError)
        throw new Error(
            errors.join(" ") ||
                flashError ||
                "Widget changes could not be saved.",
        );
    if (typeof value.error === "boolean") {
        adminResult(value);
        return;
    }
    if (value.component !== component || !isRecord(value.props))
        throw new Error("The server returned an invalid save response.");
}
