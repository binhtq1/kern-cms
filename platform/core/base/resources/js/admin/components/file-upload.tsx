import { useFieldControl } from "./field";
import { useRef, type ComponentProps } from "react";
import { Upload } from "lucide-react";
import { Button } from "./ui/button";
import { useAdminTranslation } from "@admin/lib/translate";
export function FileUpload({
    onFiles,
    children,
    buttonSize,
    id,
    "aria-label": ariaLabel,
    "aria-labelledby": ariaLabelledBy,
    "aria-describedby": ariaDescribedBy,
    "aria-invalid": ariaInvalid,
    ...props
}: Omit<ComponentProps<"input">, "onChange" | "type" | "children"> & {
    onFiles: (files: FileList) => void;
    buttonSize?: ComponentProps<typeof Button>["size"];
    children?: React.ReactNode;
}) {
    const t = useAdminTranslation();
    const field = useFieldControl(id);
    const ref = useRef<HTMLInputElement>(null);
    return (
        <>
            <Button
                {...field}
                id={id}
                aria-label={ariaLabel}
                aria-labelledby={ariaLabelledBy}
                aria-describedby={ariaDescribedBy ?? field["aria-describedby"]}
                aria-invalid={ariaInvalid ?? field["aria-invalid"]}
                variant="outline"
                size={buttonSize}
                disabled={props.disabled}
                onClick={() => ref.current?.click()}
            >
                <Upload aria-hidden="true" />
                {children ?? t("Upload")}
            </Button>
            <input
                {...props}
                ref={ref}
                type="file"
                className="sr-only"
                tabIndex={-1}
                aria-hidden="true"
                onChange={(event) => {
                    if (event.target.files?.length) onFiles(event.target.files);
                    event.target.value = "";
                }}
            />
        </>
    );
}
