declare module "sanitize-html" {
    export default function sanitizeHtml(
        html: string,
        options?: Record<string, unknown>,
    ): string;
}
