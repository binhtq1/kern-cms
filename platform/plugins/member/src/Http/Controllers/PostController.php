<?php

namespace Kern\Member\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Blog\Models\Tag;
use Kern\Blog\Services\StoreCategoryService;
use Kern\Blog\Services\StoreTagService;
use Kern\Media\Facades\RvMedia;
use Kern\Member\Forms\PostForm;
use Kern\Member\Http\Requests\PostRequest;
use Kern\Member\Models\Member;
use Kern\Member\Models\MemberActivityLog;
use Kern\Member\Supports\MemberPostData;

class PostController extends BaseController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(BaseStatusEnum::values())],
            'category' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'updated_at', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'export' => ['nullable', 'in:csv'],
        ]);
        $query = $this->memberPosts()->with('categories');
        if ($search = $filters['search'] ?? null) {
            $query->whereLike('name', '%'.$search.'%');
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($category = $filters['category'] ?? null) {
            $query->whereHas('categories', fn ($categories) => $categories->where('categories.id', $category));
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $filter => $operator) {
            if ($date = $filters[$filter] ?? null) {
                $query->whereDate('created_at', $operator, $date);
            }
        }
        $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderByDesc('id');
        if (($filters['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($query): void {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, [__('Name'), __('Categories'), __('Status'), __('Created at')], ',', '"', '');
                foreach ($query->lazy(200) as $post) {
                    $row = [$post->name, $post->categories->pluck('name')->implode(', '), $post->status->label(), $post->created_at->toDateString()];
                    $row = array_map(fn ($value): string => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : (string) $value, $row);
                    fputcsv($output, $row, ',', '"', '');
                }
                fclose($output);
            }, 'my-posts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $posts = $query->paginate($filters['per_page'] ?? 10)->withQueryString();
        $posts->through(fn (Post $post): array => [
            'id' => (string) $post->getKey(),
            'name' => $post->name,
            'image' => $post->image ? RvMedia::getImageUrl($post->image, 'thumb') : null,
            'status' => $post->status->getValue(),
            'statusLabel' => $post->status->label(),
            'categories' => $post->categories->pluck('name')->all(),
            'created_at' => $post->created_at->toDateString(),
            'edit' => route('public.member.posts.edit', $post->getKey()),
            'delete' => route('public.member.posts.destroy', $post->getKey()),
        ]);

        return Inertia::render('Posts', [
            'posts' => $posts,
            'filters' => $filters,
            'categories' => Category::query()->wherePublished()->orderBy('name')->get(['id', 'name']),
            'statuses' => BaseStatusEnum::labels(),
        ]);
    }

    public function create(MemberPostData $data)
    {
        return Inertia::render('PostForm', $data->formData('posts'));
    }

    protected function memberPosts(): Builder
    {
        return Post::query()->where('author_id', auth('member')->id())->where('author_type', Member::class);
    }

    public function store(PostRequest $request, StoreTagService $tagService, StoreCategoryService $categoryService)
    {
        app(MemberPostData::class)->validateInput($request, new Post);
        $this->processRequestData($request);

        $postForm = PostForm::create();
        $postForm
            ->saving(function (PostForm $form) use ($categoryService, $tagService, $request): void {
                /**
                 * @var Post $post
                 */
                $post = $form->getModel();
                $post
                    ->fill([...$request->only(['name', 'description', 'content', 'image', 'format_type']),
                        'author_id' => auth('member')->id(),
                        'author_type' => Member::class,
                        'status' => BaseStatusEnum::PENDING,
                    ])
                    ->save();

                MemberActivityLog::query()->create([
                    'action' => 'create_post',
                    'reference_name' => $post->name,
                    'reference_url' => route('public.member.posts.edit', $post->getKey()),
                ]);

                $tagService->execute($request, $post);

                $categoryService->execute($request, $post);

                EmailHandler::setModule(MEMBER_MODULE_SCREEN_NAME)
                    ->setVariableValues([
                        'post_name' => $post->name,
                        'post_url' => route('posts.edit', $post->getKey()),
                        'post_author' => $post->author->name,
                    ])
                    ->sendUsingTemplate('new-pending-post');
            });

        return $this
            ->httpResponse()
            ->setPreviousRoute('public.member.posts.index')
            ->setNextRoute('public.member.posts.edit', $postForm->getModel()->getKey())
            ->setData(['redirect_url' => route('public.member.posts.edit', $postForm->getModel()->getKey())])
            ->withCreatedSuccessMessage();
    }

    public function edit(Post $post)
    {
        /**
         * @var Post $post
         */
        $post = Post::query()
            ->where([
                'id' => $post->getKey(),
                'author_id' => auth('member')->id(),
                'author_type' => Member::class,
            ])
            ->firstOrFail();

        $this->pageTitle(trans('core/base::forms.edit_item', ['name' => $post->name]));

        return Inertia::render('PostForm', app(MemberPostData::class)->formData('posts', $post));
    }

    public function update(Post $post, PostRequest $request, StoreTagService $tagService, StoreCategoryService $categoryService)
    {
        /**
         * @var Post $post
         */
        $post = Post::query()
            ->where([
                'id' => $post->getKey(),
                'author_id' => auth('member')->id(),
                'author_type' => Member::class,
            ])
            ->firstOrFail();

        app(MemberPostData::class)->validateInput($request, $post);
        $this->processRequestData($request);

        $postForm = PostForm::createFromModel($post);

        $postForm
            ->saving(function (PostForm $form) use ($categoryService, $tagService, $request): void {
                /**
                 * @var Post $post
                 */
                $post = $form->getModel();

                $post
                    ->fill($request->only(['name', 'description', 'content', 'image', 'format_type']))
                    ->save();

                MemberActivityLog::query()->create([
                    'action' => 'update_post',
                    'reference_name' => $post->name,
                    'reference_url' => route('public.member.posts.edit', $post->getKey()),
                ]);

                $tagService->execute($request, $post);

                $categoryService->execute($request, $post);
            });

        return $this
            ->httpResponse()
            ->setPreviousRoute('public.member.posts.index')
            ->setData(['redirect_url' => route('public.member.posts.edit', $post->getKey())])
            ->withUpdatedSuccessMessage();
    }

    protected function processRequestData(Request $request): Request
    {
        $account = auth('member')->user();

        if ($request->hasFile('image_input')) {
            $result = RvMedia::handleUpload($request->file('image_input'), 0, $account->upload_folder);
            if (! $result['error']) {
                $file = $result['data'];
                $request->merge(['image' => $file->url]);
            }
        }

        $shortcodeCompiler = shortcode()->getCompiler();

        $request->merge([
            'content' => $shortcodeCompiler->strip(
                $request->input('content'),
                $shortcodeCompiler->whitelistShortcodes()
            ),
        ]);

        $except = [
            'status',
            'is_featured',
        ];

        foreach ($except as $item) {
            $request->request->remove($item);
        }

        return $request;
    }

    public function destroy(Post $post)
    {
        $post = Post::query()
            ->where([
                'id' => $post->getKey(),
                'author_id' => auth('member')->id(),
                'author_type' => Member::class,
            ])
            ->firstOrFail();

        $post->delete();

        MemberActivityLog::query()->create([
            'action' => 'delete_post',
            'reference_name' => $post->name,
        ]);

        return $this
            ->httpResponse()
            ->withDeletedSuccessMessage();
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'field' => ['required', Rule::in(['name', 'status', 'created_at'])],
            'value' => match ($request->input('field')) {
                'name' => ['required', 'string', 'max:250'],
                'status' => ['required', Rule::in([BaseStatusEnum::DRAFT, BaseStatusEnum::PENDING])],
                'created_at' => ['required', 'date'],
                default => ['prohibited'],
            },
        ]);
        DB::transaction(function () use ($validated, $request): void {
            $posts = $this->memberPosts()->whereIn('id', $validated['ids'])->lockForUpdate()->get();
            abort_unless($posts->count() === count($validated['ids']), 404);
            foreach ($posts as $post) {
                $post->setAttribute($validated['field'], $validated['value'])->save();
                MemberActivityLog::query()->create(['action' => 'update_post', 'reference_name' => $post->name, 'reference_url' => route('public.member.posts.edit', $post->getKey())]);
                UpdatedContentEvent::dispatch('form', $request, $post);
            }
        });

        return $this->httpResponse()->withUpdatedSuccessMessage();
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'distinct']]);
        DB::transaction(function () use ($validated): void {
            $posts = $this->memberPosts()->whereIn('id', $validated['ids'])->lockForUpdate()->get();
            abort_unless($posts->count() === count($validated['ids']), 404);
            foreach ($posts as $post) {
                $post->delete();
                MemberActivityLog::query()->create(['action' => 'delete_post', 'reference_name' => $post->name]);
            }
        });

        return $this->httpResponse()->withDeletedSuccessMessage();
    }

    public function getAllTags()
    {
        return Tag::query()->pluck('name')->all();
    }
}
