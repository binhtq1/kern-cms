<?php

namespace Kern\Block\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Block\Forms\BlockForm;
use Kern\Block\Http\Requests\BlockRequest;
use Kern\Block\Models\Block;
use Kern\Block\Tables\BlockTable;

class BlockController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/block::block.menu'), route('block.index'));
    }

    public function index(BlockTable $dataTable)
    {
        return app(AdminContentResource::class)->index('blocks');
    }

    public function create()
    {
        return app(AdminContentResource::class)->form('blocks');
    }

    public function store(BlockRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('blocks', $request)) {
            return $response;
        }

        $form = BlockForm::create();

        $form
            ->saving(function (BlockForm $form) use ($request): void {
                $form
                    ->getModel()
                    ->fill([...$request->validated(), 'user_id' => Auth::guard()->id()])
                    ->save();
            });

        return $this
            ->httpResponse()
            ->setPreviousRoute('block.index')
            ->setNextRoute('block.edit', $form->getModel()->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Block $block)
    {
        return app(AdminContentResource::class)->form('blocks', $block);
    }

    public function update(Block $block, BlockRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('blocks', $request, $block)) {
            return $response;
        }

        BlockForm::createFromModel($block)->setRequest($request)->onlyValidatedData()->save();

        return $this
            ->httpResponse()
            ->setPreviousRoute('block.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Block $block): mixed
    {
        return app(AdminContentResource::class)->destroy('blocks', $block);
    }
}
