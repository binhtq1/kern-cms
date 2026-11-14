<?php

namespace Kern\Blog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kern\ACL\Models\User;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Requests\UpdateTreeCategoryRequest;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Base\Supports\RepositoryHelper;
use Kern\Blog\Forms\CategoryForm;
use Kern\Blog\Http\Requests\CategoryRequest;
use Kern\Blog\Models\Category;
use Kern\Blog\Tables\CategoryTable;
use Kern\Language\Facades\Language;

class CategoryController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/blog::base.menu_name'))
            ->add(trans('plugins/blog::categories.menu'), route('categories.index'));
    }

    public function index(CategoryTable $dataTable)
    {
        return app(AdminContentResource::class)->index('categories');
    }

    public function create(Request $request)
    {
        $resources = app(AdminContentResource::class);
        if ($resources->isEmbeddedCategoryRequest()) {
            return response()->json(['form' => $resources->formData('categories')]);
        }

        return $resources->form('categories');
    }

    public function store(CategoryRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('categories', $request)) {
            return $response;
        }

        if ($request->input('is_default')) {
            Category::query()->where('id', '>', 0)->update(['is_default' => 0]);
        }

        $form = CategoryForm::create();
        $form
            ->saving(function (CategoryForm $form) use ($request): void {
                $form
                    ->getModel()
                    ->fill([...$request->validated(),
                        'author_id' => Auth::guard()->id(),
                        'author_type' => User::class,
                    ])
                    ->save();
            });

        $response = $this->httpResponse();

        /**
         * @var Category $category
         */
        $category = $form->getModel();

        if (app(AdminContentResource::class)->isEmbeddedCategoryRequest()) {
            return app(AdminContentResource::class)->redirect('categories', $category);
        }

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            $response->setData(['model' => $category, 'form' => app(AdminContentResource::class)->formData('categories', $category)]);
        }

        return $response
            ->setPreviousRoute('categories.index')
            ->setNextRoute('categories.edit', $category->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Category $category, Request $request)
    {
        $resources = app(AdminContentResource::class);
        if ($resources->isEmbeddedCategoryRequest()) {
            return response()->json(['form' => $resources->formData('categories', $category)]);
        }

        return $resources->form('categories', $category);
    }

    public function update(Category $category, CategoryRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('categories', $request, $category)) {
            return $response;
        }

        if ($request->input('is_default')) {
            Category::query()->where('id', '!=', $category->getKey())->update(['is_default' => 0]);
        }

        CategoryForm::createFromModel($category)->setRequest($request)->onlyValidatedData()->save();

        $response = $this->httpResponse();

        if (app(AdminContentResource::class)->isEmbeddedCategoryRequest()) {
            return app(AdminContentResource::class)->redirect('categories', $category);
        }

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            $response->setData(['model' => $category, 'form' => app(AdminContentResource::class)->formData('categories', $category)]);
        }

        return $response
            ->setPreviousRoute('categories.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Category $category): mixed
    {
        return app(AdminContentResource::class)->destroy('categories', $category);
    }

    public function updateTree(UpdateTreeCategoryRequest $request): BaseHttpResponse
    {
        abort_unless($request->user()->hasPermission('categories.edit'), 403);
        $language = app(AdminContentResource::class)->language(new Category);
        if ($language && $language !== Language::getDefaultLocaleCode()) {
            throw ValidationException::withMessages(['data' => 'Reorder categories in the default language.']);
        }
        DB::transaction(function () use ($request): void {
            $categories = RepositoryHelper::applyBeforeExecuteQuery(Category::query()->lockForUpdate(), new Category)->get()->keyBy(fn (Category $category): string => (string) $category->getKey());
            $updates = [];
            $visit = function (array $nodes, string $parent = '0', int $depth = 1) use (&$visit, &$updates, $categories): void {
                if ($depth > 5 && $nodes) {
                    throw ValidationException::withMessages(['data' => 'Category depth cannot exceed five levels.']);
                }
                foreach (array_values($nodes) as $order => $node) {
                    if (! is_array($node) || ! is_scalar($node['id'] ?? null) || ! $categories->has((string) $node['id']) || isset($updates[(string) $node['id']]) || (isset($node['children']) && ! is_array($node['children']))) {
                        throw ValidationException::withMessages(['data' => 'The category tree contains an unknown or duplicate category.']);
                    }
                    $id = (string) $node['id'];
                    $updates[$id] = ['parent_id' => $parent, 'order' => $order];
                    $visit($node['children'] ?? [], $id, $depth + 1);
                }
            };
            $visit($request->validated('data'));
            if (count($updates) !== $categories->count()) {
                throw ValidationException::withMessages(['data' => 'The category tree changed. Reload before saving.']);
            }
            foreach ($updates as $id => $values) {
                Category::query()->whereKey($id)->update($values);
            }
        });

        return $this
            ->httpResponse()
            ->withUpdatedSuccessMessage();
    }
}
