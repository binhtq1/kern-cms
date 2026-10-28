import { adminResult, isMediaList } from "@admin/lib/admin-api";
import axios from "axios";
import {
    useEffect,
    useRef,
    useState,
    type Dispatch,
    type SetStateAction,
} from "react";
import { mediaFolderUnavailable } from "../Media/media-navigation";
import type { Result } from "../lib/media-types";
export function useMediaListing({
    url,
    view,
    folder,
    search,
    filter,
    sort,
    page,
    version,
    recent,
    setFolder,
    setPage,
}: {
    url: string;
    view: string;
    folder: string | number;
    search: string;
    filter: string;
    sort: string;
    page: number;
    version: number;
    recent: string[];
    setFolder: Dispatch<SetStateAction<string | number>>;
    setPage: Dispatch<SetStateAction<number>>;
}) {
    const [result, setResult] = useState<Result>({
        files: [],
        folders: [],
        breadcrumbs: [],
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const recentRef = useRef(recent);
    recentRef.current = recent;
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setResult({ files: [], folders: [], breadcrumbs: [] });
        setError("");
        const timer = setTimeout(
            () =>
                axios
                    .get<{ error: boolean; message: string; data: Result }>(
                        url,
                        {
                            signal: controller.signal,
                            params: {
                                view_in: view,
                                recent_items: recentRef.current,
                                folder_id: folder,
                                search,
                                filter,
                                sort_by: sort,
                                paged: page,
                                posts_per_page: 30,
                            },
                        },
                    )
                    .then((response) => {
                        if (controller.signal.aborted) return;
                        const result = adminResult<Result>(
                            response.data,
                            isMediaList,
                        ).data;
                        if (
                            mediaFolderUnavailable(
                                folder,
                                undefined,
                                result.breadcrumbs,
                            )
                        ) {
                            setFolder(0);
                            setPage(1);
                            return;
                        }
                        setResult(result);
                    })
                    .catch((reason) => {
                        if (
                            !controller.signal.aborted &&
                            !axios.isCancel(reason)
                        ) {
                            if (
                                mediaFolderUnavailable(
                                    folder,
                                    reason.response?.status,
                                )
                            ) {
                                setFolder(0);
                                setPage(1);
                                return;
                            }
                            setError(
                                reason.response?.data?.message ??
                                    reason.message,
                            );
                        }
                    })
                    .finally(() => {
                        if (!controller.signal.aborted) setLoading(false);
                    }),
            200,
        );
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        url,
        view,
        folder,
        search,
        filter,
        sort,
        page,
        version,
        setFolder,
        setPage,
    ]);

    return { result, loading, error, setError };
}
