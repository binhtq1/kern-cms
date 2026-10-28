<?php

namespace Kern\Installer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Installer\Http\Requests\ChooseThemeRequest;
use Kern\Theme\Facades\Manager;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Services\ThemeService;

class ThemeController extends BaseController
{
    public function index(): View|RedirectResponse
    {
        $themes = [];
        foreach (Manager::getThemes() as $name => $theme) {
            $themes[$name] = [
                'label' => $theme['name'],
                'image' => Theme::getThemeScreenshot($name),
            ];
        }

        if (! $themes) {
            return redirect()->to(URL::temporarySignedRoute('installers.accounts.index', now()->addMinutes(30)));
        }

        return view('packages/installer::theme', compact('themes'));
    }

    public function store(ChooseThemeRequest $request, ThemeService $service): RedirectResponse
    {
        if (setting('theme') !== $request->validated('theme')) {
            $result = $service->activate($request->validated('theme'));
            if ($result['error']) {
                throw ValidationException::withMessages(['theme' => $result['message']]);
            }
        }

        return redirect()->to(URL::temporarySignedRoute('installers.accounts.index', now()->addMinutes(30)));
    }
}
