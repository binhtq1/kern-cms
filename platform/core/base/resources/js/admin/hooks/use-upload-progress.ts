import { useEffect, useState } from "react";
import {
    createUploadProgress,
    type UploadProgressItem,
} from "@admin/lib/upload-progress";

export function useUploadProgress() {
    const [items, setItems] = useState<UploadProgressItem[]>([]);
    const [progress] = useState(() => createUploadProgress(setItems));
    useEffect(() => () => progress.dispose(), [progress]);
    return { items, ...progress };
}
