import { router } from "@inertiajs/react";
import { Select } from "./ui/select";
import { useAdminTranslation } from "@admin/lib/translate";

export type LanguageChoice = {
    code: string;
    name: string;
    active: boolean;
    url: string | null;
    flag?: string;
    exists?: boolean;
};

export function LanguageSwitcher({
    languages,
}: {
    languages: LanguageChoice[];
}) {
    const t = useAdminTranslation();
    if (languages.length < 2) return null;
    return (
        <Select
            aria-label={t("Language")}
            containerClassName="min-w-40"
            value={languages.find((item) => item.active)?.code ?? ""}
            onValueChange={(value) => {
                const choice = languages.find((item) => item.code === value);
                if (choice?.url)
                    router.visit(choice.url, { preserveState: false });
            }}
        >
            {languages.map((item) => (
                <option key={item.code} value={item.code} disabled={!item.url}>
                    {item.name}
                </option>
            ))}
        </Select>
    );
}
