export type Item = {
    id: string | number;
    name: string;
    is_folder: boolean;
    full_url?: string | null;
    indirect_url?: string;
    preview_url?: string;
    thumb?: string;
    size?: string;
    mime_type?: string;
    type?: string;
    preview_type?: string | null;
    alt?: string;
    color?: string;
    created_at?: string;
    updated_at?: string;
};
export type Result = {
    files: Item[];
    folders: Item[];
    breadcrumbs: { id: string | number; name: string }[];
};
export type Props = {
    urls: {
        list: string;
        upload: string;
        folder: string;
        actions: string;
        download: string;
        chunkUpload: string;
        importUrl: string;
        folderPicker: string;
    };
    permissions: string[];
    canEmptyTrash: boolean;
    folderColors: string[];
    uploadOptions: { chunkEnabled: boolean; chunkSize: number };
    navigationKey: string;
};
