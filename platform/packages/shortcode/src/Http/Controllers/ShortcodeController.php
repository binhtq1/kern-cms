<?php

namespace Kern\Shortcode\Http\Controllers;

use Illuminate\Support\Arr;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Page\Supports\AdminPageData;
use Kern\Shortcode\Facades\Shortcode;
use Kern\Shortcode\Http\Requests\GetShortcodeDataRequest;
use Kern\Shortcode\Http\Requests\RenderBlockUiRequest;

class ShortcodeController extends BaseController
{
    public function ajaxGetAdminConfig(?string $key, GetShortcodeDataRequest $request)
    {
        $key = $key ?: $request->input('key');
        $schema = collect(app(AdminPageData::class)->shortcodes())->firstWhere('key', $key);
        abort_if($schema && ! $schema['supported'], 503, 'The shortcode integration does not provide supported React fields.');

        return $this->httpResponse()->setData($schema);
    }

    public function ajaxRenderUiBlock(RenderBlockUiRequest $request)
    {
        $name = $request->input('name');

        if (! in_array($name, array_keys(Shortcode::getAll()))) {
            return $this
                ->httpResponse()
                ->setData(null);
        }

        $code = Shortcode::generateShortcode($name, Arr::except($request->input('attributes', []), 'enable_lazy_loading'), $request->input('content'));

        $content = Shortcode::compile($code, true)->toHtml();

        return $this
            ->httpResponse()
            ->setData($content);
    }
}
