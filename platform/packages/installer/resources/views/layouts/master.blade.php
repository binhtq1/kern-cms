@php
    $steps = \Kern\Installer\InstallerStep\InstallerStep::getItems();
    $currentStep = \Kern\Installer\InstallerStep\InstallerStep::currentStep();
    $finished = Route::is('installers.final');
    $stepKeys = [
        'installers.welcome' => 'welcome',
        'installers.requirements.index' => 'requirements',
        'installers.environments.index' => 'environment',
        'installers.themes.index' => 'theme',
        'installers.accounts.index' => 'account',
        'installers.final' => 'final',
    ];
    $previousStep = array_values($steps)[$currentStep - 2] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ Str::replace('_', '-', App::getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('pageTitle', trans('packages/installer::installer.title')) | Kern CMS</title>
    <link href="{{ asset('vendor/core/core/base/images/favicon.svg') }}" rel="icon">
    <link href="{{ asset('vendor/core/packages/installer/css/style.css') }}?v={{ filemtime(package_path('installer/public/css/style.css')) }}" rel="stylesheet">
    @yield('styles')
</head>
<body @class(['is-finished' => $finished])>
    <a class="installer-skip" href="#installer-content">{{ trans('packages/installer::ui.skip_content') }}</a>
    <div class="installer-shell">
        <header class="installer-brand">
            <img src="{{ asset('vendor/core/core/base/images/logo.svg') }}" alt="" width="36" height="36">
            <span>Kern CMS</span>
            <span class="installer-brand-caption">{{ trans('packages/installer::ui.site_installation') }}</span>
        </header>
        <div class="installer-layout">
            <aside class="installer-sidebar">
                <h2>{{ trans('packages/installer::ui.setup_title') }}</h2>
                <p>{{ trans('packages/installer::ui.setup_description') }}</p>
                <nav aria-label="{{ trans('packages/installer::ui.installation_steps') }}">
                    <ol class="installer-steps">
                        @foreach ($steps as $step)
                            @php
                                $complete = $finished || $currentStep > $loop->iteration;
                                $stepLabel = isset($stepKeys[$step->getRoute()]) ? trans('packages/installer::ui.steps.' . $stepKeys[$step->getRoute()]) : $step->getLabel();
                                $stepState = trans('packages/installer::ui.' . ($complete ? 'completed' : ($currentStep === $loop->iteration ? 'current' : 'upcoming')));
                            @endphp
                            <li @class(['installer-step', 'is-current' => !$finished && $currentStep === $loop->iteration, 'is-complete' => $complete]) aria-label="{{ $stepLabel }}: {{ $stepState }}" @if($currentStep === $loop->iteration) aria-current="step" @endif>
                                @if (!$finished && $step->getRoute() && $currentStep > $loop->iteration)
                                    <a class="installer-step-target" aria-label="{{ $stepLabel }}: {{ $stepState }}" title="{{ $stepLabel }}" href="{{ URL::temporarySignedRoute($step->getRoute(), now()->addMinutes(30)) }}">
                                @else
                                    <div class="installer-step-target">
                                @endif
                                <span class="installer-step-number" aria-hidden="true">
                                    @if ($complete)
                                        <x-core::icon name="ti ti-check" />
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </span>
                                <span class="installer-step-label">
                                    {{ $stepLabel }}
                                    <span class="installer-step-state">{{ $stepState }}</span>
                                </span>
                                @if (!$finished && $step->getRoute() && $currentStep > $loop->iteration)
                                    </a>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            </aside>
            <main class="installer-card" id="installer-content" tabindex="-1">
                <header class="installer-card-header">
                    <p class="installer-progress">{{ trans('packages/installer::ui.step_of', ['current' => $currentStep, 'total' => count($steps)]) }}</p>
                    @yield('header-icon')
                    @yield('header')
                </header>
                <div class="installer-card-body">
                    @include('packages/installer::partials.alert')
                    @yield('content')
                </div>
                @hasSection('footer')
                    <footer class="installer-actions">
                        <div class="installer-pending" role="status" aria-live="polite" aria-atomic="true"></div>
                        @if (!$finished && $currentStep > 1 && $previousStep?->getRoute())
                            <a class="installer-button is-back" href="{{ URL::temporarySignedRoute($previousStep->getRoute(), now()->addMinutes(30)) }}">
                                <x-core::icon name="ti ti-arrow-left" aria-hidden="true" /> {{ trans('packages/installer::ui.back') }}
                            </a>
                        @endif
                        @yield('footer')
                    </footer>
                @endif
            </main>
        </div>
        <footer class="installer-page-footer">Kern CMS <span aria-hidden="true">·</span> {{ trans('packages/installer::ui.site_installation') }}</footer>
    </div>
    @include('packages/installer::partials.interactions')
    @yield('scripts')
</body>
</html>
