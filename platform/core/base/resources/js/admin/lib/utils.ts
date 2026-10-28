import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
export const adminQueryOptions = { queryStringArrayFormat: "indices" as const };
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function normalizeSearch(value: string): string {
    return value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d")
        .replace(/Đ/g, "D")
        .trim()
        .toLocaleLowerCase();
}
