<?php

namespace Kern\Theme\Http\Controllers;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\Breadcrumb;
use Kern\Setting\Http\Controllers\Concerns\InteractsWithSettings;
use Kern\Theme\Facades\Manager;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Facades\ThemeOption;
use Kern\Theme\Forms\CustomCSSForm;
use Kern\Theme\Forms\CustomHTMLForm;
use Kern\Theme\Forms\CustomJSForm;
use Kern\Theme\Forms\RobotsTxtEditorForm;
use Kern\Theme\Http\Requests\CustomCssRequest;
use Kern\Theme\Http\Requests\CustomHtmlRequest;
use Kern\Theme\Http\Requests\CustomJsRequest;
use Kern\Theme\Http\Requests\RobotsTxtRequest;
use Kern\Theme\Http\Requests\UpdateOptionsRequest;
use Kern\Theme\Services\ThemeService;
use Kern\Theme\Supports\AdminThemeData;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ThemeController extends BaseController
{
    use InteractsWithSettings;

    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('packages/theme::theme.appearance'));
    }

    public function index()
    {
        abort_unless(config('packages.theme.general.display_theme_manager_in_admin_panel', true), 404);

        $this->pageTitle(trans('packages/theme::theme.name'));

        if (File::exists(theme_path('.DS_Store'))) {
            File::delete(theme_path('.DS_Store'));
        }

        $themes = Manager::getThemes();

        return Inertia::render('Theme/Index', [
            'themes' => collect($themes)->map(fn (array $theme, string $slug): array => [
                'slug' => $slug, 'name' => $theme['name'], 'description' => $theme['description'] ?? '',
                'version' => $theme['version'] ?? '', 'author' => $theme['author'] ?? '', 'inherit' => $theme['inherit'] ?? null,
                'screenshot' => Theme::getThemeScreenshot($slug), 'active' => Theme::getThemeName() === $slug,
            ])->values()->all(),
            'urls' => ['activate' => route('theme.active'), 'remove' => route('theme.remove')],
            'permissions' => ['activate' => request()->user()->hasPermission('theme.activate'), 'remove' => request()->user()->hasPermission('theme.remove')],
        ]);
    }

    public function getOptions(?string $id = null)
    {
        return Inertia::render('Theme/Options', app(AdminThemeData::class)->options($id));
    }

    public function postUpdate(UpdateOptionsRequest $request)
    {
        foreach (app(AdminThemeData::class)->validateOptions($request) as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);

                $field = ThemeOption::getField($key);

                if ($field && Arr::get($field, 'clean_tags', true)) {
                    $value = BaseHelper::clean(strip_tags((string) $value));
                }
            }

            ThemeOption::setOption($key, $value);
        }

        ThemeOption::saveOptions();

        return $this
            ->httpResponse()
            ->withUpdatedSuccessMessage();
    }

    public function postActivateTheme(Request $request, ThemeService $themeService)
    {
        abort_unless(config('packages.theme.general.display_theme_manager_in_admin_panel', true), 404);

        abort_unless($request->user()->hasPermission('theme.activate'), 403);
        $request->validate(['theme' => ['required', Rule::in(array_keys(Manager::getThemes()))]]);
        $result = $themeService->activate($request->input('theme'));

        return $this
            ->httpResponse()
            ->setError($result['error'])
            ->setMessage($result['message']);
    }

    public function getCustomCss()
    {
        $this->pageTitle(trans('packages/theme::theme.custom_css'));

        $form = CustomCSSForm::create();

        return Inertia::render('Theme/Editor', ['title' => 'Custom CSS', 'fields' => app(AdminThemeData::class)->editorFields($form), 'submit' => $form->getFormOption('url')]);
    }

    public function postCustomCss(CustomCssRequest $request)
    {
        File::delete(theme_path(Theme::getThemeName().'/public/css/style.integration.css'));

        $file = Theme::getStyleIntegrationPath();
        $css = $request->input('custom_css');
        $css = strip_tags((string) $css);

        if (empty($css)) {
            File::delete($file);
        } else {
            $saved = BaseHelper::saveFileData($file, $css, false);

            if (! $saved) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage(
                        trans('packages/theme::theme.folder_is_not_writeable', ['name' => File::dirname($file)])
                    );
            }
        }

        return $this
            ->httpResponse()
            ->withUpdatedSuccessMessage();
    }

    public function getCustomJs()
    {
        abort_unless(config('packages.theme.general.enable_custom_js'), 404);

        $this->pageTitle(trans('packages/theme::theme.custom_js'));

        $form = CustomJSForm::create();

        return Inertia::render('Theme/Editor', ['title' => 'Custom JavaScript', 'fields' => app(AdminThemeData::class)->editorFields($form), 'submit' => $form->getFormOption('url')]);
    }

    public function postCustomJs(CustomJsRequest $request)
    {
        abort_unless(config('packages.theme.general.enable_custom_js'), 404);

        return $this->performUpdate($request->validated());
    }

    public function postRemoveTheme(Request $request, ThemeService $themeService)
    {
        abort_unless(config('packages.theme.general.display_theme_manager_in_admin_panel', true), 404);

        abort_unless($request->user()->hasPermission('theme.remove'), 403);
        $request->validate(['theme' => ['required', Rule::in(array_keys(Manager::getThemes()))]]);
        $theme = strtolower($request->input('theme'));

        if (in_array($theme, BaseHelper::scanFolder(theme_path()))) {
            try {
                $result = $themeService->remove($theme);

                return $this
                    ->httpResponse()
                    ->setError($result['error'])
                    ->setMessage($result['message']);
            } catch (Exception $exception) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($exception->getMessage());
            }
        }

        return $this
            ->httpResponse()
            ->setError()
            ->setMessage(trans('packages/theme::theme.theme_is_not_existed'));
    }

    public function getCustomHtml()
    {
        abort_unless(config('packages.theme.general.enable_custom_html'), 404);

        $this->pageTitle(trans('packages/theme::theme.custom_html'));

        $form = CustomHTMLForm::create();

        return Inertia::render('Theme/Editor', ['title' => 'Custom HTML', 'fields' => app(AdminThemeData::class)->editorFields($form), 'submit' => $form->getFormOption('url')]);
    }

    public function postCustomHtml(CustomHtmlRequest $request)
    {
        abort_unless(config('packages.theme.general.enable_custom_html'), 404);

        $data = [];

        foreach ($request->validated() as $key => $value) {
            $data[$key] = BaseHelper::clean($value);
        }

        return $this->performUpdate($data);
    }

    public function getRobotsTxt()
    {
        abort_unless(config('packages.theme.general.enable_robots_txt_editor'), 404);

        $this->pageTitle(trans('packages/theme::theme.robots_txt_editor'));

        $form = RobotsTxtEditorForm::create();

        return Inertia::render('Theme/Editor', ['title' => 'Robots.txt', 'fields' => app(AdminThemeData::class)->editorFields($form), 'submit' => $form->getFormOption('url')]);
    }

    public function postRobotsTxt(RobotsTxtRequest $request)
    {
        abort_unless(config('packages.theme.general.enable_robots_txt_editor'), 404);

        $path = public_path('robots.txt');

        if (! File::isWritable($path)) {
            return $this->robotsWriteError($request);
        }

        try {
            if ($request->hasFile('robots_txt_file')) {
                $request->file('robots_txt_file')->move(public_path(), 'robots.txt');
            } elseif (File::put($path, $request->input('robots_txt_content')) === false) {
                return $this->robotsWriteError($request);
            }
        } catch (FileException) {
            return $this->robotsWriteError($request);
        }

        return $this->httpResponse()->withUpdatedSuccessMessage();
    }

    protected function robotsWriteError(RobotsTxtRequest $request): BaseHttpResponse|RedirectResponse
    {
        $message = trans('packages/theme::theme.robots_txt_not_writable', ['path' => public_path('robots.txt')]);
        if ($request->header('X-Inertia')) {
            return redirect()->route('theme.robots-txt')->with('error_msg', $message)->withErrors(['_request' => $message]);
        }

        return $this->httpResponse()->setError()->setMessage($message);
    }
}
