<?php

namespace Kern\Media\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;

class PublicMediaController extends BaseController
{
    public function show(string $hash, string $id)
    {
        $originId = MediaFile::isUsingStringId() ? $id : hexdec($id);

        abort_if(sha1($id) !== $hash, 404);

        $mediaFile = MediaFile::query()
            ->whereKey($originId)
            ->firstOrFail();

        if ($mediaFile->visibility === 'private') {
            abort_unless(auth()->check() && auth()->user()->hasPermission('media.index'), 403);
            abort_if(setting('user_can_only_view_own_media', false) && ! auth()->user()->isSuperUser() && (string) $mediaFile->user_id !== (string) auth()->id(), 403);

            return response()->download(Storage::disk('local')->path($mediaFile->url));
        }

        $response = Http::withoutVerifying()->get(RvMedia::url($mediaFile->url));

        abort_if($response->failed(), 403, $response->reason());

        $body = $response->toPsrResponse()->getBody();

        return Response::streamDownload(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(1024);
            }
        }, headers: $response->headers());
    }
}
