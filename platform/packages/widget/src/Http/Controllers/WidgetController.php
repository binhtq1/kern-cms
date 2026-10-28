<?php

namespace Kern\Widget\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\Breadcrumb;
use Kern\Language\Supports\AdminAppearanceLanguages;
use Kern\Widget\Events\RenderingWidgetSettings;
use Kern\Widget\Facades\WidgetGroup;
use Kern\Widget\Http\Requests\WidgetRequest;
use Kern\Widget\Models\Widget;
use Kern\Widget\Supports\AdminWidgetData;

class WidgetController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('packages/theme::theme.appearance'))
            ->add(trans('packages/widget::widget.name'), route('widgets.index'));
    }

    public function index()
    {
        RenderingWidgetSettings::dispatch();

        return app(AdminWidgetData::class)->index();
    }

    public function update(WidgetRequest $request, AdminWidgetData $adminWidgets)
    {
        $this->setLanguageContext();
        $items = $adminWidgets->validateItems($request->string('sidebar_id')->toString(), $request->input('items', []));
        try {
            $sidebarId = $request->input('sidebar_id');

            $themeName = Widget::getThemeName();

            $adminWidgets->save($sidebarId, $items);

            if ($request->header('X-Inertia')) {
                return $this->httpResponse()->setPreviousUrl(route('widgets.index', $request->only('ref_lang')))->setMessage(trans('packages/widget::widget.save_success'));
            }

            $widgetAreas = Widget::query()->where([
                'sidebar_id' => $sidebarId,
                'theme' => $themeName,
            ])->get();

            return $this
                ->httpResponse()
                ->setData($widgetAreas->toArray())
                ->setMessage(trans('packages/widget::widget.save_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function destroy(WidgetRequest $request)
    {
        $this->setLanguageContext();
        try {
            Widget::query()->where([
                'theme' => Widget::getThemeName(),
                'sidebar_id' => $request->input('sidebar_id'),
                'position' => $request->input('position'),
                'widget_id' => $request->input('widget_id'),
            ])->delete();

            $sidebarId = $request->input('sidebar_id');

            $themeName = Widget::getThemeName();

            $widgetAreas = Widget::query()->where([
                'sidebar_id' => $sidebarId,
                'theme' => $themeName,
            ])->get();

            return $this
                ->httpResponse()
                ->setData($widgetAreas->toArray())
                ->setMessage(trans('packages/widget::widget.delete_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function saveAll(Request $request, AdminWidgetData $adminWidgets)
    {
        $this->setLanguageContext();
        RenderingWidgetSettings::dispatch();
        $validated = $request->validate([
            'groups' => ['required', 'array', 'max:100'],
            'groups.*.sidebar_id' => ['required', 'string', 'distinct', Rule::in(array_keys(WidgetGroup::getGroups()))],
            'groups.*.items' => ['present', 'array', 'max:100'],
        ]);
        $groups = [];
        foreach ($validated['groups'] as $index => $group) {
            try {
                $groups[] = ['sidebar_id' => $group['sidebar_id'], 'items' => $adminWidgets->validateItems($group['sidebar_id'], $group['items'])];
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($errors, $key): array => ['groups.'.$index.'.'.$key => $errors])->all());
            }
        }
        DB::transaction(function () use ($groups, $adminWidgets): void {
            foreach ($groups as $group) {
                $adminWidgets->save($group['sidebar_id'], $group['items']);
            }
        });

        return $this->httpResponse()->setPreviousUrl(route('widgets.index', $request->only('ref_lang')))->setMessage(trans('packages/widget::widget.save_success'));
    }

    protected function setLanguageContext(): void
    {
        if (is_plugin_active('language')) {
            app(AdminAppearanceLanguages::class)->context();
        }
    }
}
