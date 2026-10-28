import { useId, type ReactNode } from "react";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    CardFooter,
} from "./ui/card";
export function FormSection({
    title,
    description,
    children,
    footer,
    contentClassName,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    contentClassName?: string;
}) {
    const id = useId();
    return (
        <Card aria-labelledby={id}>
            <CardHeader>
                <CardTitle id={id}>{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent className={contentClassName ?? "space-y-5"}>
                {children}
            </CardContent>
            {footer && <CardFooter>{footer}</CardFooter>}
        </Card>
    );
}
