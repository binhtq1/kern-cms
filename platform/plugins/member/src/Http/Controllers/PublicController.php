<?php

namespace Kern\Member\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Blog\Models\Post;
use Kern\Media\Chunks\Exceptions\UploadMissingFileException;
use Kern\Media\Chunks\Handler\DropZoneUploadHandler;
use Kern\Media\Chunks\Receiver\FileReceiver;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Member\Forms\Fronts\ProfileForm;
use Kern\Member\Http\Requests\AvatarRequest;
use Kern\Member\Http\Requests\SettingRequest;
use Kern\Member\Http\Requests\UpdatePasswordRequest;
use Kern\Member\Http\Resources\ActivityLogResource;
use Kern\Member\Models\Member;
use Kern\Member\Models\MemberActivityLog;
use Kern\SeoHelper\Facades\SeoHelper;
use Kern\SeoHelper\SeoOpenGraph;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Facades\Theme;

class PublicController extends BaseController
{
    public function getAuthor(string $slug)
    {
        $slug = SlugHelper::getSlug($slug, SlugHelper::getPrefix(Member::class), Member::class);
        abort_unless($slug, 404);

        $condition = [
            'id' => $slug->reference_id,
            'status' => BaseStatusEnum::PUBLISHED,
        ];

        if (Auth::guard()->check() && request('preview')) {
            Arr::forget($condition, 'status');
        }

        $author = Member::query()
            ->where($condition)
            ->with(['slugable'])
            ->firstOrFail();

        SeoHelper::setTitle($author->name)->setDescription($author->description);

        $meta = new SeoOpenGraph;
        if ($author->avatar) {
            $meta->setImage(RvMedia::getImageUrl($author->avatar));
        }
        $meta->setDescription($author->description);
        $meta->setUrl($author->url);
        $meta->setTitle($author->name);
        $meta->setType('article');

        SeoHelper::setSeoOpenGraph($meta);

        Theme::breadcrumb()->add($author->name, $author->url);

        do_action(BASE_ACTION_PUBLIC_RENDER_SINGLE, MEMBER_MODULE_SCREEN_NAME, $author);

        $posts = Post::query()
            ->wherePublished()
            ->where([
                'author_id' => $author->getKey(),
                'author_type' => Member::class,
            ])
            ->orderByDesc('created_at')
            ->paginate(12);

        return Theme::scope('author', compact('author', 'posts'), 'plugins/member::themes.author')->render();
    }

    public function getDashboard()
    {
        $user = auth('member')->user();

        $this->pageTitle(__('Dashboard'));

        return Inertia::render('Dashboard', [
            'statistics' => is_plugin_active('blog') ? collect([
                BaseStatusEnum::PUBLISHED => trans('plugins/blog::member.published_posts'),
                BaseStatusEnum::PENDING => trans('plugins/blog::member.pending_posts'),
                BaseStatusEnum::DRAFT => trans('plugins/blog::member.draft_posts'),
            ])->map(fn (string $label, string $status): array => [
                'label' => $label,
                'count' => $user->posts()->where('status', $status)->count(),
            ])->values()->all() : [],
            'extraStatistics' => apply_filters(MEMBER_TOP_STATISTIC_FILTER, null),
        ]);
    }

    public function getSettings()
    {
        $user = auth('member')->user();

        return Inertia::render('Settings', [
            'profile' => [
                ...$user->only(['first_name', 'last_name', 'phone', 'description', 'gender']),
                'dob' => $user->dob?->format('Y-m-d') ?: '',
            ],
            'extensionTabs' => apply_filters('member_settings_register_content_tabs', null),
            'extensionContent' => apply_filters('member_settings_register_content_tab_inside', null),
        ]);
    }

    public function postSettings(SettingRequest $request)
    {
        ProfileForm::createFromModel(auth('member')->user())
            ->saving(function (ProfileForm $form) use ($request): void {
                $model = $form->getModel();

                $model->update(Arr::except($request->validated(), 'email'));
            });

        MemberActivityLog::query()->create([
            'action' => 'update_setting',
        ]);

        return $this
            ->httpResponse()
            ->setNextRoute('public.member.settings')
            ->setMessage(__('Update profile successfully!'));
    }

    public function postSecurity(UpdatePasswordRequest $request)
    {
        $request->user('member')->update([
            'password' => $request->input('password'),
        ]);

        MemberActivityLog::query()->create(['action' => 'update_security']);

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/member::dashboard.password_update_success'));
    }

    public function postAvatar(AvatarRequest $request)
    {
        try {
            $account = auth('member')->user();

            $result = RvMedia::uploadFromBlob($request->file('avatar_file'), folderSlug: $account->upload_folder);

            if ($result['error']) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($result['message']);
            }

            $file = $result['data'];

            $mediaFile = MediaFile::query()->find($account->avatar_id);
            $mediaFile?->forceDelete();

            $account->avatar_id = $file->id;
            $account->save();

            MemberActivityLog::query()->create([
                'action' => 'changed_avatar',
            ]);

            return $this
                ->httpResponse()
                ->setMessage(trans('plugins/member::dashboard.update_avatar_success'))
                ->setData(['url' => RvMedia::url($file->url)]);
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function getActivityLogs()
    {
        $activities = MemberActivityLog::query()
            ->where('member_id', auth('member')->id())
            ->latest()
            ->paginate();

        return $this
            ->httpResponse()
            ->setData(ActivityLogResource::collection($activities))
            ->toApiResponse();
    }

    public function postPickerUpload(Request $request)
    {
        $request->validate(['file' => RvMedia::imageValidationRule()]);
        $result = RvMedia::handleUpload($request->file('file'), 0, $request->user('member')->upload_folder);
        if ($result['error']) {
            return $this->httpResponse()->setError()->setMessage($result['message']);
        }

        $file = $result['data'];

        return $this->httpResponse()->setData([
            'id' => (string) $file->getKey(),
            'name' => $file->name,
            'url' => $file->url,
            'src' => RvMedia::url($file->url),
            'mime_type' => $file->mime_type,
            'type' => 'image',
        ]);
    }

    public function postUpload(Request $request)
    {
        $account = auth('member')->user();

        if (! RvMedia::isChunkUploadEnabled()) {
            $validator = Validator::make($request->all(), [
                'file.0' => RvMedia::imageValidationRule(),
            ]);

            if ($validator->fails()) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($validator->getMessageBag()->first());
            }

            $result = RvMedia::handleUpload(Arr::first($request->file('file')), 0, $account->upload_folder);

            if ($result['error']) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($result['message']);
            }

            return $this
                ->httpResponse()
                ->setData($result['data']);
        }

        try {
            // Create the file receiver
            $receiver = new FileReceiver('file', $request, DropZoneUploadHandler::class);
            // Check if the upload is success, throw exception or return response you need
            if ($receiver->isUploaded() === false) {
                throw new UploadMissingFileException;
            }
            // Receive the file
            $save = $receiver->receive();
            // Check if the upload has finished (in chunk mode it will send smaller files)
            if ($save->isFinished()) {
                $result = RvMedia::handleUpload($save->getFile(), 0, $account->upload_folder);

                if (! $result['error']) {
                    return $this
                        ->httpResponse()
                        ->setData($result['data']);
                }

                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($result['message']);
            }
            // We are in chunk mode, lets send the current progress
            $handler = $save->handler();

            return response()->json([
                'done' => $handler->getPercentageDone(),
                'status' => true,
            ]);
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function postUploadFromEditor(Request $request)
    {
        $account = auth('member')->user();

        return RvMedia::uploadFromEditor($request, 0, $account->upload_folder);
    }
}
