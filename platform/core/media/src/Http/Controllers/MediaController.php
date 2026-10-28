<?php

namespace Kern\Media\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Http\Requests\MediaListRequest;
use Kern\Media\Http\Resources\FileResource;
use Kern\Media\Http\Resources\FolderResource;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Kern\Media\Models\MediaSetting;
use Kern\Media\Repositories\Interfaces\MediaFileInterface;
use Kern\Media\Repositories\Interfaces\MediaFolderInterface;
use Kern\Media\Services\MoveAdminMedia;
use Kern\Media\Services\ThumbnailService;
use Kern\Media\Services\UploadsManager;
use Kern\Media\Supports\Zipper;
use League\Flysystem\UnableToWriteFile;
use Throwable;

/**
 * @since 19/08/2015 08:05 AM
 */
class MediaController extends BaseController
{
    public function __construct(
        protected MediaFileInterface $fileRepository,
        protected MediaFolderInterface $folderRepository,
        protected UploadsManager $uploadManager
    ) {}

    public function getMedia()
    {
        $this->pageTitle(trans('core/media::media.menu_name'));

        return Inertia::render('Media/Index', ['urls' => [
            'list' => route('media.list'), 'upload' => route('media.picker-upload'), 'importUrl' => route('media.download_url'), 'folder' => route('media.folders.create'), 'folderPicker' => route('media.folder-picker'),
            'chunkUpload' => route('media.files.upload'), 'actions' => route('media.global_actions'), 'download' => route('media.download'),
        ], 'navigationKey' => request()->user()->getKey().':'.(setting('media_random_hash') ?: '21d06709fe1d3abdf0e35ddda89c4b282'), 'canEmptyTrash' => (! setting('user_can_only_view_own_media', false) || request()->user()->isSuperUser()) && RvMedia::hasPermission('files.destroy') && RvMedia::hasPermission('folders.destroy'), 'permissions' => RvMedia::getPermissions(), 'folderColors' => RvMedia::getFolderColors(), 'uploadOptions' => ['chunkEnabled' => RvMedia::isChunkUploadEnabled(), 'chunkSize' => (int) RvMedia::getConfig('chunk.chunk_size', 1048576)]]);
    }

    public function getPicker(Request $request): array
    {
        $data = $request->validate([
            'path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'folder_id' => ['sometimes', 'integer', 'min:0'],
            'type' => ['sometimes', 'nullable', Rule::in(['image'])],
        ]);
        $ownOnly = setting('user_can_only_view_own_media', false) && ! $request->user()->isSuperUser();
        $query = MediaFile::query()->orderByDesc('id');
        $folderQuery = MediaFolder::query()->orderBy('name');
        if ($ownOnly) {
            $query->where('user_id', $request->user()->getKey());
            $folderQuery->where('user_id', $request->user()->getKey());
        }
        $folderId = (int) ($data['folder_id'] ?? 0);
        $breadcrumbs = [];
        if (array_key_exists('folder_id', $data)) {
            $folder = $folderId ? (clone $folderQuery)->findOrFail($folderId) : null;
            $query->where('folder_id', $folderId);
            $visited = [];
            while ($folder && ! isset($visited[$folder->getKey()])) {
                $visited[$folder->getKey()] = true;
                array_unshift($breadcrumbs, ['id' => (string) $folder->getKey(), 'name' => $folder->name]);
                $folder = $folder->parent_id ? (clone $folderQuery)->find($folder->parent_id) : null;
            }
        }
        if (($data['type'] ?? null) === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        }
        if (! empty($data['search'])) {
            $query->where('name', 'like', '%'.$data['search'].'%');
        }
        if (! empty($data['path'])) {
            $query->where('url', $data['path']);
        }
        $result = $query->paginate(24)->through(fn (MediaFile $file): array => [
            'id' => (string) $file->getKey(), 'name' => $file->name, 'url' => $file->url,
            'src' => $file->visibility === 'public' ? RvMedia::url($file->url) : $file->indirect_url,
            'mime_type' => $file->mime_type, 'size' => $file->size,
        ])->toArray();

        return [...$result,
            'folders' => (clone $folderQuery)->where('parent_id', $folderId)->get(['id', 'name'])->map(fn (MediaFolder $folder): array => ['id' => (string) $folder->getKey(), 'name' => $folder->name])->all(),
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    public function getFolderPicker(Request $request)
    {
        $query = MediaFolder::query()->orderBy('name');
        if (setting('user_can_only_view_own_media', false) && ! $request->user()->isSuperUser()) {
            $query->where('user_id', $request->user()->getKey());
        }

        return $query->get(['id', 'name', 'parent_id']);
    }

    public function uploadPicker(Request $request)
    {
        abort_unless(RvMedia::hasPermission('files.create'), 403);
        $request->validate(['file' => ['required', 'file', 'max:'.(int) (RvMedia::getServerConfigMaxUploadFileSize() / 1024)], 'folder_id' => ['sometimes', 'integer', 'min:0']]);
        if ($folderId = $request->integer('folder_id')) {
            $folderQuery = MediaFolder::query();
            if (setting('user_can_only_view_own_media', false) && ! $request->user()->isSuperUser()) {
                $folderQuery->where('user_id', $request->user()->getKey());
            }
            $folderQuery->findOrFail($folderId);
        }
        $result = RvMedia::handleUpload($request->file('file'), $request->input('folder_id', 0));
        if ($result['error']) {
            return RvMedia::responseError($result['message']);
        }

        $file = $result['data'];

        return RvMedia::responseSuccess(['id' => (string) $file->getKey(), 'name' => $file->name, 'url' => $file->url, 'src' => $file->visibility === 'public' ? RvMedia::url($file->url) : $file->indirect_url, 'mime_type' => $file->mime_type]);
    }

    public function getPopup()
    {
        return $this->getMedia();
    }

    public function getList(MediaListRequest $request)
    {
        $files = [];
        $folders = [];
        $breadcrumbs = [];

        $selectedFileId = $request->input('selected_file_id');

        if ($request->has('is_popup') && $selectedFileId) {
            $currentFile = MediaFile::query()->where(
                ['id' => $selectedFileId],
                ['folder_id']
            )->first();

            if ($currentFile) {
                $request->merge(['folder_id' => $currentFile->folder_id]);
            }
        }

        $paramsFolder = [];

        $paramsFile = [
            'order_by' => [
                'is_folder' => 'DESC',
            ],
            'paginate' => [
                'per_page' => $request->integer('posts_per_page', 30),
                'current_paged' => $request->integer('paged', 1),
            ],
            'selected_file_id' => $selectedFileId,
            'is_popup' => $request->input('is_popup'),
            'filter' => $request->input('filter', 'everything'),
        ];

        $orderBy = $this->transformOrderBy($request->input('sort_by'));

        if (count($orderBy) > 1) {
            $paramsFile['order_by'][$orderBy[0]] = $orderBy[1];
        }

        $search = $request->input('search');

        if ($search) {
            $paramsFolder['condition'] = [
                ['media_folders.name', 'LIKE', '%'.$search.'%'],
            ];

            $paramsFile['condition'] = [
                ['media_files.name', 'LIKE', '%'.$search.'%'],
            ];
        }

        if (setting('user_can_only_view_own_media', false) && ! $request->user()->isSuperUser()) {
            $paramsFile['condition'][] = ['media_files.user_id', '=', $request->user()->getKey()];
            $paramsFolder['condition'][] = ['media_folders.user_id', '=', $request->user()->getKey()];
        }

        $folderId = $request->input('folder_id', 0);

        switch ($request->input('view_in')) {
            case 'all_media':
                $breadcrumbs = [
                    [
                        'id' => 0,
                        'name' => trans('core/media::media.all_media'),
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M15 8h.01"></path>
                            <path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"></path>
                            <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"></path>
                            <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"></path>
                        </svg>',
                    ],
                ];

                $queried = $this->fileRepository->getFilesByFolderId($folderId, $paramsFile, true, $paramsFolder);

                $folders = FolderResource::collection($queried->where('is_folder', 1));

                $files = FileResource::collection($queried->where('is_folder', 0));

                break;

            case 'trash':
                $breadcrumbs = [
                    [
                        'id' => 0,
                        'name' => trans('core/media::media.trash'),
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M4 7l16 0"></path>
                            <path d="M10 11l0 6"></path>
                            <path d="M14 11l0 6"></path>
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                        </svg>',
                    ],
                ];

                $queried = $this->fileRepository->getTrashed(
                    $folderId,
                    $paramsFile,
                    true,
                    $paramsFolder
                );

                $folders = FolderResource::collection($queried->where('is_folder', 1));

                $files = FileResource::collection($queried->where('is_folder', 0));

                break;

            case 'recent':
                $breadcrumbs = [
                    [
                        'id' => 0,
                        'name' => trans('core/media::media.recent'),
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                            <path d="M12 7v5l3 3"></path>
                        </svg>',
                    ],
                ];

                if (! count($request->input('recent_items', []))) {
                    break;
                }

                $queried = $this->fileRepository->getFilesByFolderId(
                    0,
                    array_merge($paramsFile, ['recent_items' => $request->input('recent_items', []), 'all_folders' => true]),
                    false,
                    $paramsFolder
                );

                $files = FileResource::collection($queried);

                break;

            case 'favorites':
                $breadcrumbs = [
                    [
                        'id' => 0,
                        'name' => trans('core/media::media.favorites'),
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"></path>
                        </svg>',
                    ],
                ];

                $favoriteItems = MediaSetting::query()
                    ->where([
                        'key' => 'favorites',
                        'user_id' => Auth::guard()->id(),
                    ])->first();

                if (! empty($favoriteItems)) {
                    $fileIds = collect($favoriteItems->value)
                        ->filter(fn (array $item): bool => ! filter_var($item['is_folder'], FILTER_VALIDATE_BOOLEAN))
                        ->pluck('id')
                        ->all();

                    $folderIds = collect($favoriteItems->value)
                        ->filter(fn (array $item): bool => filter_var($item['is_folder'], FILTER_VALIDATE_BOOLEAN))
                        ->pluck('id')
                        ->all();

                    $paramsFile = array_merge_recursive($paramsFile, [
                        'condition' => [
                            ['media_files.id', 'IN', $fileIds],
                        ],
                    ]);

                    $paramsFolder = array_merge_recursive($paramsFolder, [
                        'condition' => [
                            ['media_folders.id', 'IN', $folderIds],
                        ],
                    ]);

                    $queried = $this->fileRepository->getFilesByFolderId(
                        $folderId,
                        [...$paramsFile, 'all_folders' => true],
                        true,
                        $paramsFolder
                    );

                    $folders = FolderResource::collection($queried->where('is_folder', 1));

                    $files = FileResource::collection($queried->where('is_folder', 0));
                }

                break;
        }

        $breadcrumbs = array_merge($breadcrumbs, $this->getBreadcrumbs($request));

        return RvMedia::responseSuccess([
            'files' => $files,
            'folders' => $folders,
            'breadcrumbs' => $breadcrumbs,
            'selected_file_id' => $selectedFileId,
        ]);
    }

    protected function transformOrderBy(?string $orderBy): array
    {
        $result = explode('-', $orderBy ?? 'name-asc');
        if (count($result) !== 2 || ! in_array($result[0], ['name', 'created_at', 'updated_at', 'size'], true) || ! in_array($result[1], ['asc', 'desc'], true)) {
            return ['name', 'asc'];
        }

        return $result;
    }

    protected function getBreadcrumbs(Request $request): array
    {
        $folderId = $request->input('folder_id');

        if (! $folderId) {
            return [];
        }

        if ($request->input('view_in') == 'trash') {
            $folder = MediaFolder::query()->withTrashed()->find($folderId);
        } else {
            $folder = MediaFolder::query()->find($folderId);
        }

        if (empty($folder)) {
            return [];
        }

        $breadcrumbs = [
            [
                'name' => $folder->name,
                'id' => $folder->id,
            ],
        ];

        $child = $this->folderRepository->getBreadcrumbs($folder->parent_id);
        if (! empty($child)) {
            return array_merge($child, $breadcrumbs);
        }

        return $breadcrumbs;
    }

    public function postGlobalActions(Request $request, ThumbnailService $thumbnailService)
    {
        $response = RvMedia::responseError(trans('core/media::media.invalid_action'));

        $type = $request->input('action');
        if (in_array($type, ['move', 'copy'], true)) {
            app(MoveAdminMedia::class)->execute($request, $type === 'copy');

            return RvMedia::responseSuccess([], trans('core/base::notices.update_success_message'));
        }

        switch ($type) {
            case 'trash':
                $error = false;
                foreach ($request->input('selected') as $item) {
                    $id = $item['id'];
                    if (! $item['is_folder']) {
                        try {
                            $this->fileRepository->deleteBy(['id' => $id]);
                        } catch (Exception $exception) {
                            BaseHelper::logError($exception);
                            $error = true;
                        }
                    } else {
                        $this->folderRepository->deleteFolder($id);
                    }
                }

                if ($error) {
                    $response = RvMedia::responseError(trans('core/media::media.trash_error'));

                    break;
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.trash_success'));

                break;

            case 'restore':
                $error = false;
                foreach ($request->input('selected') as $item) {
                    $id = $item['id'];
                    if (! $item['is_folder']) {
                        try {
                            $this->fileRepository->restoreBy(['id' => $id]);
                        } catch (Exception $exception) {
                            BaseHelper::logError($exception);
                            $error = true;
                        }
                    } else {
                        $this->folderRepository->restoreFolder($id);
                    }
                }

                if ($error) {
                    $response = RvMedia::responseError(trans('core/media::media.restore_error'));

                    break;
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.restore_success'));

                break;

            case 'make_copy':
                foreach ($request->input('selected', []) as $item) {
                    $id = $item['id'];
                    if (! $item['is_folder']) {
                        /**
                         * @var MediaFile $file
                         */
                        $file = MediaFile::query()->find($id);

                        if (! $file) {
                            break;
                        }

                        $this->copyFile($file);
                    } else {
                        $oldFolder = MediaFolder::query()->find($id);

                        if (! $oldFolder) {
                            break;
                        }

                        $folderData = $oldFolder->replicate()->toArray();

                        $folderData['slug'] = $this->folderRepository->createSlug(
                            $oldFolder->name,
                            $oldFolder->parent_id
                        );
                        $folderData['name'] = $oldFolder->name.'-(copy)';
                        $folderData['user_id'] = Auth::guard()->id();
                        $folder = $this->folderRepository->create($folderData);

                        $files = $this->fileRepository->getFilesByFolderId($id, [], false);
                        foreach ($files as $file) {
                            $this->copyFile($file, $folder->id);
                        }

                        $children = $this->folderRepository->getAllChildFolders($id);
                        foreach ($children as $parentId => $child) {
                            if ($parentId != $oldFolder->getKey()) {
                                $folder = MediaFolder::query()->find($parentId);

                                if (! $folder) {
                                    break;
                                }

                                $folderData = $folder->replicate()->toArray();

                                $folderData['slug'] = $this->folderRepository->createSlug(
                                    $oldFolder->name,
                                    $oldFolder->parent_id
                                );
                                $folderData['name'] = $oldFolder->name.'-(copy)';
                                $folderData['user_id'] = Auth::guard()->id();
                                $folderData['parent_id'] = $folder->id;
                                $folder = MediaFolder::query()->create($folderData);

                                $parentFiles = $this->fileRepository->getFilesByFolderId($parentId, [], false);
                                foreach ($parentFiles as $parentFile) {
                                    $this->copyFile($parentFile, $folder->id);
                                }
                            }

                            foreach ($child as $sub) {
                                /**
                                 * @var MediaFolder $sub
                                 */
                                $subFiles = $this->fileRepository->getFilesByFolderId($sub->getKey(), [], false);

                                $subFolderData = $sub->replicate()->toArray();

                                $subFolderData['user_id'] = Auth::guard()->id();
                                $subFolderData['parent_id'] = $folder->id;

                                $sub = MediaFolder::query()->create($subFolderData);

                                foreach ($subFiles as $subFile) {
                                    $this->copyFile($subFile, $sub->getKey());
                                }
                            }
                        }

                        $allFiles = Storage::allFiles($this->folderRepository->getFullPath($oldFolder->getKey()));
                        foreach ($allFiles as $file) {
                            Storage::copy($file, str_replace($oldFolder->slug, $folder->slug, $file));
                        }
                    }
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.copy_success'));

                break;

            case 'delete':
                foreach ($request->input('selected') as $item) {
                    $id = $item['id'];
                    if (! $item['is_folder']) {
                        try {
                            $this->fileRepository->forceDelete(['id' => $id]);
                        } catch (Exception $exception) {
                            BaseHelper::logError($exception);
                        }
                    } else {
                        $this->folderRepository->deleteFolder($id, true);
                    }
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.delete_success'));

                break;

            case 'favorite':
                $meta = MediaSetting::query()->firstOrCreate([
                    'key' => 'favorites',
                    'user_id' => Auth::guard()->id(),
                ]);

                if (! empty($meta->value)) {
                    $meta->value = array_merge($meta->value, $request->input('selected', []));
                } else {
                    $meta->value = $request->input('selected', []);
                }

                $meta->save();

                $response = RvMedia::responseSuccess([], trans('core/media::media.favorite_success'));

                break;

            case 'remove_favorite':
                $meta = MediaSetting::query()->firstOrCreate([
                    'key' => 'favorites',
                    'user_id' => Auth::guard()->id(),
                ]);

                if (! empty($meta)) {
                    $value = $meta->value;
                    if (! empty($value)) {
                        foreach ($value as $key => $item) {
                            foreach ($request->input('selected') as $selectedItem) {
                                if ($item['is_folder'] == $selectedItem['is_folder'] && $item['id'] == $selectedItem['id']) {
                                    unset($value[$key]);
                                }
                            }
                        }

                        $meta->value = $value;

                        $meta->save();
                    }
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.remove_favorite_success'));

                break;

            case 'crop':
                $validated = Validator::validate($request->input(), [
                    'imageId' => ['required', 'string', 'exists:media_files,id'],
                    'cropData' => ['required', 'json'],
                ]);

                $data = json_decode($validated['cropData'], true);

                $cropData = Validator::validate($data, [
                    'x' => ['required', 'numeric', 'min:0'],
                    'y' => ['required', 'numeric', 'min:0'],
                    'width' => ['required', 'numeric', 'min:1'],
                    'height' => ['required', 'numeric', 'min:1'],
                ]);

                /**
                 * @var MediaFile $file
                 */
                $file = MediaFile::query()->findOrFail($validated['imageId']);

                if (! $file->canGenerateThumbnails()) {
                    $response = RvMedia::responseError(trans('core/media::media.failed_to_crop_image'));

                    break;
                }

                $fileUrl = $file->url;
                $parsedUrl = parse_url($fileUrl);

                if (isset($parsedUrl['query'])) {
                    $fileUrl = str_replace('?'.$parsedUrl['query'], '', $fileUrl);
                }

                try {
                    $thumbnailService
                        ->setImage(RvMedia::getRealPath($fileUrl))
                        ->setSize((int) $cropData['width'], (int) $cropData['height'])
                        ->setCoordinates((int) $cropData['x'], (int) $cropData['y'])
                        ->setDestinationPath(File::dirname($fileUrl))
                        ->setFileName(File::name($fileUrl).'.'.File::extension($fileUrl))
                        ->save('crop');

                    $croppedSize = Storage::disk(RvMedia::getConfig('disk'))->size($fileUrl);
                } catch (UnableToWriteFile $exception) {
                    $message = $exception->getMessage();

                    if (! RvMedia::isUsingCloud()) {
                        $message = trans('core/media::media.unable_to_write', ['folder' => RvMedia::getUploadPath()]);
                    }

                    return RvMedia::responseError($message);
                } catch (Throwable $exception) {
                    return RvMedia::responseError($exception->getMessage());
                }

                $file->url = $fileUrl.'?v='.time();
                $file->size = $croppedSize;
                $file->save();

                RvMedia::generateThumbnails($file);

                $response = RvMedia::responseSuccess([], trans('core/media::media.crop_success'));

                break;

            case 'rename':
                Validator::validate($request->input(), [
                    'selected' => ['required', 'array'],
                    'selected.*.id' => ['required', 'string'],
                    'selected.*.name' => ['required', 'string', 'max:120'],
                    'selected.*.is_folder' => ['required', 'boolean'],
                ]);

                foreach ($request->input('selected') as $item) {
                    $id = $item['id'];

                    if (! $item['is_folder']) {
                        /**
                         * @var MediaFile $file
                         */
                        $file = MediaFile::query()->find($id);

                        if (! empty($file)) {
                            RvMedia::renameFile(
                                file: $file,
                                newName: $item['name'],
                                renameOnDisk: Arr::get($item, 'rename_physical_file', false)
                            );
                        }
                    } else {
                        $name = $item['name'];
                        /**
                         * @var MediaFolder $folder
                         */
                        $folder = MediaFolder::query()->find($id);

                        if (! empty($folder)) {
                            RvMedia::renameFolder(
                                folder: $folder,
                                newName: $name,
                                renameOnDisk: Arr::get($item, 'rename_physical_file', false)
                            );
                        }
                    }
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.rename_success'));

                break;

            case 'alt_text':
                Validator::validate($request->input(), [
                    'selected' => ['required', 'array'],
                    'selected.*.id' => ['required', 'exists:media_files,id'],
                    'selected.*.alt' => ['nullable', 'string', 'max:220'],
                ]);

                foreach ($request->input('selected') as $item) {
                    if (! $item['id']) {
                        continue;
                    }

                    MediaFile::query()->where('id', $item['id'])->update(['alt' => $item['alt']]);
                }

                $response = RvMedia::responseSuccess([], trans('core/media::media.update_alt_text_success'));

                break;
            case 'empty_trash':
                $this->fileRepository->emptyTrash();
                $this->folderRepository->emptyTrash();

                $response = RvMedia::responseSuccess([], trans('core/media::media.empty_trash_success'));

                break;

            case 'properties':
                Validator::validate($request->input(), [
                    'color' => ['required', 'string', Rule::in(RvMedia::getFolderColors())],
                    'selected' => ['required', 'array'],
                    'selected.*' => ['required', 'string', 'exists:media_folders,id'],
                ]);

                MediaFolder::query()->whereIn('id', $request->input('selected'))->update([
                    'color' => $request->input('color'),
                ]);

                $response = RvMedia::responseSuccess([], trans('core/media::media.update_properties_success'));

                break;
        }

        return $response;
    }

    protected function copyFile(MediaFile $file, int|string|null $newFolderId = null)
    {
        $file = $file->replicate();
        $file->user_id = Auth::guard()->id();

        if ($newFolderId == null) {
            $file->name = $file->name.'-(copy)';

            $path = '';

            $folderPath = File::dirname($file->url);
            if ($folderPath) {
                $path = $folderPath.'/'.$path;
            }

            $path = $path.File::name($file->url).'-(copy)'.'.'.File::extension($file->url);

            $filePath = RvMedia::getRealPath($file->url);
            if (Storage::exists($filePath)) {
                $content = File::get($filePath);

                $this->uploadManager->saveFile($path, $content);
                $file->url = $path;

                RvMedia::generateThumbnails($file);
            }
        } else {
            $file->url = str_replace(
                RvMedia::getRealPath(File::dirname($file->url)),
                RvMedia::getRealPath($this->folderRepository->getFullPath($newFolderId)),
                $file->url
            );

            $file->folder_id = $newFolderId;
        }

        unset($file->is_folder);
        unset($file->slug);
        unset($file->parent_id);
        unset($file->color);

        $file->save();

        return $file;
    }

    public function download(Request $request)
    {
        $request->validate(['selected' => ['required', 'array', 'min:1', 'max:200']]);
        $items = $request->input('selected');
        if (count($items) === 1 && ! $items[0]['is_folder']) {
            $file = MediaFile::withTrashed()->findOrFail($items[0]['id']);
            abort_unless(Storage::exists($file->url), 404);

            return Storage::download($file->url, File::basename($file->url));
        }

        $archive = Storage::disk('local')->path('download-'.Str::uuid().'.zip');
        $zip = (new Zipper)->make($archive);
        $entries = [];
        try {
            foreach ($items as $item) {
                if ($item['is_folder']) {
                    $folder = MediaFolder::withTrashed()->findOrFail($item['id']);
                    $this->addFolderToArchive($zip, $folder, $folder->slug, $entries);
                } else {
                    $file = MediaFile::withTrashed()->findOrFail($item['id']);
                    $this->addFileToArchive($zip, $file, '', $entries);
                }
            }
            $zip->close();
        } catch (Throwable $exception) {
            $zip->close();
            File::delete($archive);
            throw $exception;
        }

        abort_unless(File::exists($archive), 404);

        return response()->download($archive, 'media.zip')->deleteFileAfterSend();
    }

    protected function addFolderToArchive(Zipper $zip, MediaFolder $folder, string $prefix, array &$entries, array $seen = []): void
    {
        abort_if(in_array((string) $folder->getKey(), $seen, true), 422);
        $seen[] = (string) $folder->getKey();
        foreach ($folder->files()->withTrashed()->get() as $file) {
            $this->addFileToArchive($zip, $file, $prefix, $entries);
        }
        foreach (MediaFolder::withTrashed()->where('parent_id', $folder->getKey())->get() as $child) {
            $this->addFolderToArchive($zip, $child, $prefix.'/'.$child->slug, $entries, $seen);
        }
    }

    protected function addFileToArchive(Zipper $zip, MediaFile $file, string $prefix, array &$entries): void
    {
        abort_unless(Storage::exists($file->url), 404);
        $name = ($prefix ? trim($prefix, '/').'/' : '').File::basename($file->url);
        if (in_array($name, $entries, true)) {
            $name = ($prefix ? trim($prefix, '/').'/' : '').$file->getKey().'-'.File::basename($file->url);
        }
        $entries[] = $name;
        if (RvMedia::isUsingCloud()) {
            $zip->addString($name, Storage::get($file->url));
        } else {
            $zip->add(Storage::path($file->url), $name);
        }
    }
}
