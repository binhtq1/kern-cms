import {
    ContentResourceIndex,
    type ContentIndexProps,
} from "@admin/components/content-resource-index";
import {
    LanguageSwitcher,
    type LanguageChoice,
} from "@admin/components/language-switcher";

export default function Menus(
    props: ContentIndexProps & { languages?: LanguageChoice[] },
) {
    return (
        <ContentResourceIndex
            {...props}
            extraActions={
                <LanguageSwitcher languages={props.languages ?? []} />
            }
        />
    );
}
