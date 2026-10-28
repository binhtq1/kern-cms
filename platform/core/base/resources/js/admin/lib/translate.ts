import { useCallback } from "react";
import { usePage } from "@inertiajs/react";

type Replacements = Record<string, string | number>;
type TranslationProps = {
    admin?: {
        locale?: string;
        translations?: Record<string, string>;
    };
    [key: string]: unknown;
};

export function translateAdmin(
    message: string,
    dictionary: Record<string, string> = {},
    replacements: Replacements = {},
): string {
    const translation = Object.prototype.hasOwnProperty.call(
        dictionary,
        message,
    )
        ? dictionary[message]
        : message;

    return Object.entries(replacements).reduce(
        (text, [key, value]) => text.split(`:${key}`).join(String(value)),
        typeof translation === "string" ? translation : message,
    );
}

export function useAdminTranslation() {
    const dictionary = usePage<TranslationProps>().props.admin?.translations;

    return useCallback(
        (message: string, replacements: Replacements = {}) =>
            translateAdmin(message, dictionary, replacements),
        [dictionary],
    );
}
