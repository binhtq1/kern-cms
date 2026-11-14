<?php

namespace Kern\Blog\Http\Controllers;

use Illuminate\Http\Request;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Blog\Forms\PostForm;
use Kern\Blog\Http\Requests\PostRequest;
use Kern\Blog\Models\Post;
use Kern\Blog\Services\StoreCategoryService;
use Kern\Blog\Services\StoreTagService;
use Kern\Blog\Tables\PostTable;
use Kern\Member\Models\Member;

class PostController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/blog::base.menu_name'))
            ->add(trans('plugins/blog::posts.menu_name'), route('posts.index'));
    }

    public function index(PostTable $dataTable)
    {
        return app(AdminContentResource::class)->index('posts');
    }

    public function create()
    {
        return app(AdminContentResource::class)->form('posts');
    }

    public function store(
        PostRequest $request,
        StoreTagService $tagService,
        StoreCategoryService $categoryService
    ) {
        if ($response = app(AdminContentResource::class)->prepare('posts', $request)) {
            return $response;
        }

        $form = PostForm::create()->setRequest($request);
        $this->savePostForm($form, $request);

        $post = $form->getModel();

        $tagService->execute($request, $post);

        $categoryService->execute($request, $post);

        return $this
            ->httpResponse()
            ->setPreviousRoute('posts.index')
            ->setNextRoute('posts.edit', $post->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Post $post)
    {
        return app(AdminContentResource::class)->form('posts', $post);
    }

    public function update(
        Post $post,
        PostRequest $request,
        StoreTagService $tagService,
        StoreCategoryService $categoryService,
    ) {
        if ($response = app(AdminContentResource::class)->prepare('posts', $request, $post)) {
            return $response;
        }

        $form = PostForm::createFromModel($post)->setRequest($request);
        $this->savePostForm($form, $request);

        /**
         * @var Post $post
         */
        $post = $form->getModel();

        $tagService->execute($request, $post);

        $categoryService->execute($request, $post);

        return $this
            ->httpResponse()
            ->setPreviousRoute('posts.index')
            ->withUpdatedSuccessMessage();
    }

    protected function savePostForm(PostForm $form, PostRequest $request): void
    {
        $form->saving(function (PostForm $form) use ($request): void {
            $model = $form->getModel();
            $model->fill($request->safe()->except(['author_id', 'author_type']));
            if (is_plugin_active('member') && $request->filled('author_id')) {
                $model->author_id = $request->input('author_id');
                $model->author_type = Member::class;
            }
            $model->save();
        });
    }

    public function destroy(Post $post): mixed
    {
        return app(AdminContentResource::class)->destroy('posts', $post);
    }

    public function getWidgetRecentPosts(Request $request): BaseHttpResponse
    {
        $limit = $request->integer('paginate', 10);
        $limit = $limit > 0 ? $limit : 10;

        $posts = Post::query()
            ->with(['slugable'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->httpResponse()->setData([
            'columns' => [['key' => 'index', 'label' => '#'], ['key' => 'title', 'label' => trans('core/base::tables.name')], ['key' => 'created_at', 'label' => trans('core/base::tables.created_at')]],
            'records' => $posts->map(fn (Post $post, int $index): array => [
                'index' => $index + 1,
                'title' => $post->name,
                'url' => $post->slug && in_array(strtolower((string) parse_url($post->url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $post->url : null,
                'target' => '_blank',
                'created_at' => $post->created_at?->toIso8601String(),
            ])->all(),
            'empty' => ['title' => __('No results found'), 'description' => trans('plugins/blog::posts.no_new_post_now')],
        ]);

    }
}
