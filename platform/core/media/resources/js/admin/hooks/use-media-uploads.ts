import { notify } from "@admin/components/toast";
import { useAdminTranslation } from "@admin/lib/translate";
import { useUploadProgress } from "@admin/hooks/use-upload-progress";
import { chunkUploadPercent } from "@admin/lib/upload-progress";
import { createUuid } from "@admin/lib/uuid";
import axios from "axios";
import {
    useEffect,
    useRef,
    useCallback,
    type Dispatch,
    type RefObject,
    type SetStateAction,
} from "react";
import { runMediaUpload } from "../Media/media-upload-lifecycle";
import type { Props } from "../lib/media-types";
type UploadSource = {
    files: File[];
    folder: string | number;
    context: number;
    urls: Pick<Props["urls"], "upload" | "chunkUpload">;
    uploadOptions: Props["uploadOptions"];
};
export function useMediaUploads({
    urls,
    folder,
    uploadOptions,
    mediaContext,
    setBusy,
    setError,
    setVersion,
    recordRecent,
}: {
    urls: Props["urls"];
    folder: string | number;
    uploadOptions: Props["uploadOptions"];
    mediaContext: RefObject<number>;
    setBusy: Dispatch<SetStateAction<boolean>>;
    setError: Dispatch<SetStateAction<string>>;
    setVersion: Dispatch<SetStateAction<number>>;
    recordRecent: (id: unknown) => void;
}) {
    const t = useAdminTranslation();
    const setNotice = (message: string) => notify(message);
    const progress = useUploadProgress();
    const sourceRef = useRef<UploadSource | null>(null);
    const { clear: clearProgress } = progress;
    const dismissUpload = useCallback(() => {
        sourceRef.current = null;
        clearProgress();
    }, [clearProgress]);
    const uploadController = useRef<AbortController | null>(null);
    useEffect(
        () => () => {
            const controller = uploadController.current;
            uploadController.current = null;
            controller?.abort();
        },
        [],
    );

    async function upload(files: FileList | null) {
        if (!files?.length || uploadController.current) return;
        const source: UploadSource = {
            files: Array.from(files),
            folder,
            context: mediaContext.current,
            urls: { upload: urls.upload, chunkUpload: urls.chunkUpload },
            uploadOptions: { ...uploadOptions },
        };
        sourceRef.current = source;
        await runUpload(
            source,
            progress.start(source.files),
            source.files.map((_, index) => index),
        );
    }

    async function retryUpload(id: string) {
        const source = sourceRef.current;
        if (uploadController.current || !source) return;
        const batch = progress.retry(id);
        if (batch) await runUpload(source, batch, [batch.index]);
    }

    async function runUpload(
        source: UploadSource,
        batch: ReturnType<typeof progress.start>,
        indices: number[],
    ) {
        setBusy(true);
        setError("");
        const controller = new AbortController();
        uploadController.current = controller;
        const { files: uploads, folder, uploadOptions, urls, context } = source;
        const isOwner = () => uploadController.current === controller;
        const isCurrent = () =>
            isOwner() &&
            !controller.signal.aborted &&
            mediaContext.current === context;
        await runMediaUpload(
            async () => {
                const failures: string[] = [];
                for (const fileIndex of indices) {
                    const file = uploads[fileIndex];
                    if (controller.signal.aborted || !isOwner()) break;
                    batch.update(fileIndex, { status: "uploading" });
                    try {
                        const size = uploadOptions.chunkEnabled
                            ? Math.max(1, uploadOptions.chunkSize)
                            : Math.max(1, file.size);
                        const chunks = Math.max(1, Math.ceil(file.size / size));
                        const uuid = createUuid();
                        for (let chunk = 0; chunk < chunks; chunk++) {
                            const data = new FormData();
                            data.append(
                                "file",
                                chunks === 1
                                    ? file
                                    : file.slice(
                                          chunk * size,
                                          (chunk + 1) * size,
                                      ),
                                file.name,
                            );
                            data.append("folder_id", String(folder));
                            if (uploadOptions.chunkEnabled) {
                                data.append("dzuuid", uuid);
                                data.append("dzchunkindex", String(chunk));
                                data.append(
                                    "dztotalchunkcount",
                                    String(chunks),
                                );
                                data.append("dzchunksize", String(size));
                                data.append(
                                    "dztotalfilesize",
                                    String(file.size),
                                );
                                data.append(
                                    "dzchunkbyteoffset",
                                    String(chunk * size),
                                );
                            }
                            const response = await axios.post(
                                uploadOptions.chunkEnabled
                                    ? urls.chunkUpload
                                    : urls.upload,
                                data,
                                {
                                    signal: controller.signal,
                                    onUploadProgress: (event) => {
                                        if (
                                            isOwner() &&
                                            !controller.signal.aborted
                                        ) {
                                            batch.update(fileIndex, {
                                                percent: chunkUploadPercent(
                                                    file.size,
                                                    size,
                                                    chunk,
                                                    event,
                                                ),
                                            });
                                        }
                                    },
                                },
                            );
                            if (response.data.error)
                                throw new Error(response.data.message);
                            if (controller.signal.aborted || !isOwner()) break;
                            if (chunk === chunks - 1 && isCurrent())
                                recordRecent(response.data.data?.id);
                        }
                        if (isOwner() && !controller.signal.aborted)
                            batch.update(fileIndex, { status: "uploaded" });
                    } catch (reason) {
                        if (controller.signal.aborted || !isOwner()) break;
                        if (axios.isCancel(reason)) throw reason;
                        const error = reason as {
                            response?: { data?: { message?: string } };
                            message?: string;
                        };
                        const message =
                            error.response?.data?.message ||
                            error.message ||
                            t("Upload failed");
                        batch.update(fileIndex, {
                            status: "error",
                            error: message,
                        });
                        failures.push(`${file.name}: ${message}`);
                    }
                }
                if (failures.length) throw new Error(failures.join("\n"));
            },
            {
                isOwner,
                isCurrent,
                isCancelled: axios.isCancel,
                complete: () => setNotice(t("Upload complete")),
                fail: (reason) => {
                    const error = reason as {
                        response?: { data?: { message?: string } };
                        message: string;
                    };
                    setError(error.response?.data?.message ?? error.message);
                    notify(
                        error.response?.data?.message ?? error.message,
                        "error",
                    );
                },
                release: () => {
                    uploadController.current = null;
                    batch.cancel();
                    setBusy(false);
                },
                refresh: () => setVersion((value) => value + 1),
            },
        );
    }

    return {
        upload,
        retryUpload,
        uploadItems: progress.items,
        dismissUpload,
        uploadController,
    };
}
