import { axios, HttpClient } from "./utilities";
import lodash from "lodash";

window._ = lodash;
window.axios = axios;
window.$httpClient = new HttpClient();

const headersFor = (config) => {
    const url = new URL(
        config.url ?? window.location.href,
        new URL(config.baseURL ?? window.location.origin, window.location.href),
    );
    if (url.origin !== window.location.origin) return {};
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return token ? { "X-CSRF-TOKEN": token } : {};
};

axios.interceptors.request.use((config) => {
    Object.assign((config.headers ??= {}), headersFor(config));
    return config;
});
window.$httpClient.setup((request) => {
    request.beforeSend(() => request.withHeaders(headersFor(request.config)));
});
