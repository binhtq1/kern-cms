<?php

namespace Kern\Gallery\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Gallery\Forms\GalleryForm;
use Kern\Gallery\Http\Requests\GalleryRequest;
use Kern\Gallery\Models\Gallery;
use Kern\Gallery\Tables\GalleryTable;

class GalleryController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/gallery::gallery.galleries'), route('galleries.index'));
    }

    public function index(GalleryTable $dataTable)
    {
        return app(AdminContentResource::class)->index('galleries');
    }

    public function create()
    {
        return app(AdminContentResource::class)->form('galleries');
    }

    public function store(GalleryRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('galleries', $request)) {
            return $response;
        }

        $form = GalleryForm::create();

        $form->saving(function (GalleryForm $form) use ($request): void {
            $form
                ->getModel()
                ->fill([...$request->validated(), 'user_id' => Auth::guard()->id()])
                ->save();
        });

        return $this
            ->httpResponse()
            ->setPreviousRoute('galleries.index')
            ->setNextRoute('galleries.edit', $form->getModel()->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Gallery $gallery)
    {
        return app(AdminContentResource::class)->form('galleries', $gallery);
    }

    public function update(Gallery $gallery, GalleryRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('galleries', $request, $gallery)) {
            return $response;
        }

        GalleryForm::createFromModel($gallery)->setRequest($request)->onlyValidatedData()->save();

        return $this
            ->httpResponse()
            ->setPreviousRoute('galleries.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Gallery $gallery): mixed
    {
        return app(AdminContentResource::class)->destroy('galleries', $gallery);
    }
}
