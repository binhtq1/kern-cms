<?php

namespace Kern\Blog\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Kern\ACL\Models\User;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Blog\Forms\TagForm;
use Kern\Blog\Http\Requests\TagRequest;
use Kern\Blog\Models\Tag;
use Kern\Blog\Tables\TagTable;

class TagController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/blog::base.menu_name'))
            ->add(trans('plugins/blog::tags.menu'), route('tags.index'));
    }

    public function index(TagTable $dataTable)
    {
        return app(AdminContentResource::class)->index('tags');
    }

    public function create()
    {
        return app(AdminContentResource::class)->form('tags');
    }

    public function store(TagRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('tags', $request)) {
            return $response;
        }

        $form = TagForm::create();

        $form
            ->saving(function (TagForm $form) use ($request): void {
                $form
                    ->getModel()
                    ->fill([...$request->validated(),
                        'author_id' => Auth::guard()->id(),
                        'author_type' => User::class,
                    ])
                    ->save();
            });

        return $this
            ->httpResponse()
            ->setPreviousRoute('tags.index')
            ->setNextRoute('tags.edit', $form->getModel()->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Tag $tag)
    {
        return app(AdminContentResource::class)->form('tags', $tag);
    }

    public function update(Tag $tag, TagRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('tags', $request, $tag)) {
            return $response;
        }

        TagForm::createFromModel($tag)->setRequest($request)->onlyValidatedData()->save();

        return $this
            ->httpResponse()
            ->setPreviousRoute('tags.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Tag $tag): mixed
    {
        return app(AdminContentResource::class)->destroy('tags', $tag);
    }

    public function getAllTags()
    {
        return Tag::query()->pluck('name')->all();
    }
}
