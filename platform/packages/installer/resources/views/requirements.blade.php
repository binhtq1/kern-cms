@extends('packages/installer::layouts.master')

@section('pageTitle', trans('packages/installer::ui.steps.requirements'))
@section('header')
    <h1>{{ trans('packages/installer::ui.steps.requirements') }}</h1>
    <p>{{ trans('packages/installer::ui.requirements_description') }}</p>
@endsection
@section('content')
    @php
        $blocked = isset($requirements['errors']) || !$phpSupportInfo['supported'];
        $needsAttention = $blocked || collect($requirements['requirements'])->flatten()->contains(false);
    @endphp
    <div @class(['installer-notice', 'is-success' => !$needsAttention, 'is-warning' => $needsAttention]) role="status">
        <x-core::icon :name="$needsAttention ? 'ti ti-alert-circle' : 'ti ti-circle-check'" aria-hidden="true" />
        <p>{{ trans('packages/installer::ui.' . ($needsAttention ? 'requirements_attention' : 'requirements_ready')) }}</p>
    </div>
    <div class="installer-requirements">
        @foreach ($requirements['requirements'] as $type => $requirement)
            @php
                $checks = collect($requirement);
                if ($type === 'php') $checks = $checks->prepend($phpSupportInfo['supported']);
            @endphp
            <section class="installer-requirement-group">
                <div class="installer-requirement-heading">
                    <h2>{{ trans('packages/installer::ui.requirement_groups.' . $type) }}</h2>
                    <span>{{ trans('packages/installer::ui.checks_passed', ['passed' => $checks->filter()->count(), 'total' => $checks->count()]) }}</span>
                </div>
                <dl>
                    @if ($type === 'php')
                        <div class="installer-requirement-row">
                            <dt>PHP ≥ {{ $phpSupportInfo['minimum'] }}</dt>
                            <dd @class(['is-ok' => $phpSupportInfo['supported'], 'is-failed' => !$phpSupportInfo['supported']])>
                                <span>{{ $phpSupportInfo['current'] }}</span>
                                <x-core::icon :name="$phpSupportInfo['supported'] ? 'ti ti-circle-check' : 'ti ti-alert-circle'" aria-hidden="true" />
                                <span class="installer-sr-only">{{ trans('packages/installer::ui.' . ($phpSupportInfo['supported'] ? 'passed' : 'needs_attention')) }}</span>
                            </dd>
                        </div>
                    @endif
                    @foreach ($requirement as $extension => $enabled)
                        <div class="installer-requirement-row">
                            <dt>{{ $type === 'permissions' ? $extension : match (strtolower($extension)) {
                                'openssl' => 'OpenSSL', 'pdo' => 'PDO', 'json' => 'JSON', 'curl' => 'cURL', 'gd' => 'GD', 'xml' => 'XML', default => $extension,
                            } }}</dt>
                            <dd @class(['is-ok' => $enabled, 'is-failed' => !$enabled])>
                                <span>{{ trans('packages/installer::ui.' . ($enabled ? ($type === 'permissions' ? 'writable' : 'available') : ($type === 'permissions' ? 'not_writable' : 'missing'))) }}</span>
                                <x-core::icon :name="$enabled ? 'ti ti-circle-check' : 'ti ti-alert-circle'" aria-hidden="true" />
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
@endsection
@section('footer')
    <a class="installer-button" data-navigation-message="{{ trans('packages/installer::ui.checking_requirements') }}" href="{{ URL::temporarySignedRoute('installers.requirements.index', now()->addMinutes(30)) }}">
        <x-core::icon name="ti ti-refresh" aria-hidden="true" /> {{ trans('packages/installer::ui.check_again') }}
    </a>
    @if ($blocked)
        <button class="installer-button is-primary" type="button" disabled>{{ trans('packages/installer::ui.continue') }}</button>
    @else
        <a class="installer-button is-primary" data-navigation-message="{{ trans('packages/installer::ui.opening_environment') }}" href="{{ URL::temporarySignedRoute('installers.environments.index', now()->addMinutes(30)) }}">
            {{ trans('packages/installer::ui.continue') }} <x-core::icon name="ti ti-arrow-right" aria-hidden="true" />
        </a>
    @endif
@endsection
