import { cleanRichText } from "./rich-text-safety";
export { cleanRichText } from "./rich-text-safety";
export function SafeRichText({
    html,
    className = "",
}: {
    html: string;
    className?: string;
}) {
    return (
        <div
            className={`max-w-none break-words text-sm leading-relaxed [&_h1]:my-3 [&_h1]:text-2xl [&_h2]:my-3 [&_h2]:text-xl [&_h3]:my-2 [&_h3]:text-lg [&_h4]:font-semibold [&_h5]:font-semibold [&_h6]:font-semibold [&_th]:border [&_th]:p-2 [&_td]:border [&_td]:p-2 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_img]:max-w-full [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_a]:text-primary [&_a]:underline [&_table]:block [&_table]:overflow-auto ${className}`}
            dangerouslySetInnerHTML={{ __html: cleanRichText(html) }}
        />
    );
}
