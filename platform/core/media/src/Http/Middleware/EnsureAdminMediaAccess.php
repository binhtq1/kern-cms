<?php

namespace Kern\Media\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMediaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('media.picker', 'media.picker-upload')) {
            $request->validate(['folder_id' => ['sometimes', 'integer', 'min:0']]);
        }
        $own = setting('user_can_only_view_own_media', false) && ! $request->user()->isSuperUser();
        foreach (['folder_id', 'parent_id', 'folderId', 'destination_id'] as $key) {
            if ($request->filled($key) && (string) $request->input($key) !== '0') {
                abort_unless(is_scalar($request->input($key)), 422);
                $folder = MediaFolder::withTrashed()->findOrFail($request->input($key));
                abort_if($own && (string) $folder->user_id !== (string) $request->user()->getKey(), 403);
            }
        }
        if ($request->filled('selected_file_id')) {
            abort_unless(is_scalar($request->input('selected_file_id')), 422);
            $file = MediaFile::withTrashed()->findOrFail($request->input('selected_file_id'));
            abort_if($own && (string) $file->user_id !== (string) $request->user()->getKey(), 403);
        }
        $permission = match ($request->route()->getName()) {
            'media.files.upload', 'media.files.upload.from.editor', 'media.download_url' => 'files.create',
            'media.folders.create' => 'folders.create',
            default => null,
        };
        if ($permission) {
            abort_unless(RvMedia::hasPermission($permission), 403);
        }
        if ($request->routeIs('media.download', 'media.global_actions') && is_array($request->input('selected')) && $request->input('action') !== 'properties') {
            $selected = [];
            foreach ($request->input('selected') as $item) {
                abort_unless(is_array($item), 422);
                $folder = filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                abort_if($folder === null, 422);
                $selected[] = [...$item, 'is_folder' => $folder];
            }
            $request->merge(['selected' => $selected]);
        }
        if ($request->routeIs('media.download')) {
            $request->validate(['selected' => ['required', 'array', 'min:1', 'max:200']]);
            foreach ($request->input('selected') as $item) {
                $model = ! empty($item['is_folder']) ? MediaFolder::withTrashed()->findOrFail($item['id']) : MediaFile::withTrashed()->findOrFail($item['id']);
                abort_if($own && (string) $model->user_id !== (string) $request->user()->getKey(), 403);
                if ($own && $model instanceof MediaFolder) {
                    $this->assertFolderOwnership($model, (string) $request->user()->getKey());
                }
            }
        }
        if ($request->routeIs('media.global_actions')) {
            $action = $request->input('action');
            $verb = match ($action) {
                'trash' => 'trash', 'delete', 'empty_trash' => 'destroy',
                'favorite', 'remove_favorite' => 'favorite', 'make_copy', 'copy' => 'create',
                'restore', 'crop', 'rename', 'alt_text', 'properties', 'move' => 'edit',
                default => null,
            };
            abort_unless($verb, 422);
            if ($action === 'empty_trash') {
                abort_if($own, 403);
                abort_unless(RvMedia::hasPermission('files.destroy') && RvMedia::hasPermission('folders.destroy'), 403);
            } else {
                $selected = $action === 'crop' ? [['id' => $request->input('imageId'), 'is_folder' => false]] : $request->input('selected');
                Validator::make(['selected' => $selected], ['selected' => ['required', 'array', 'min:1', 'max:200']])->validate();
                foreach ($selected as $item) {
                    $isFolder = $action === 'properties' || ($action !== 'alt_text' && is_array($item) && in_array($item['is_folder'] ?? false, [true, 1, '1', 'true'], true));
                    $id = is_array($item) ? ($item['id'] ?? null) : $item;
                    abort_unless(is_scalar($id), 422);
                    $model = $isFolder ? MediaFolder::withTrashed()->findOrFail($id) : MediaFile::withTrashed()->findOrFail($id);
                    abort_if($own && (string) $model->user_id !== (string) $request->user()->getKey(), 403);
                    abort_unless(RvMedia::hasPermission(($isFolder ? 'folders.' : 'files.').$verb), 403);
                    if ($isFolder && $own) {
                        $this->assertFolderOwnership($model, (string) $request->user()->getKey());
                    }
                }
            }
        }

        return $next($request);
    }

    protected function assertFolderOwnership(MediaFolder $folder, string $userId, array $seen = []): void
    {
        abort_if(in_array((string) $folder->getKey(), $seen, true), 422);
        $seen[] = (string) $folder->getKey();
        abort_if($folder->files()->withoutGlobalScope('ownMedia')->withTrashed()->where('user_id', '!=', $userId)->exists(), 403);
        foreach (MediaFolder::withoutGlobalScope('ownMedia')->withTrashed()->where('parent_id', $folder->getKey())->get() as $child) {
            abort_if((string) $child->user_id !== $userId, 403);
            $this->assertFolderOwnership($child, $userId, $seen);
        }
    }
}
