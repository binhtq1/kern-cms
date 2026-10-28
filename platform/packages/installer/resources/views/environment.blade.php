@extends('packages/installer::layouts.master')

@section('pageTitle', trans('packages/installer::ui.steps.environment'))
@section('header')
    <h1>{{ trans('packages/installer::ui.steps.environment') }}</h1>
    <p>{{ trans('packages/installer::ui.environment_description') }}</p>
@endsection
@section('content')
    @php
        $databaseNameMaxLength = \Kern\Installer\Http\Requests\SaveEnvironmentRequest::DATABASE_NAME_MAX_LENGTH;
        $sqlitePathMaxLength = \Kern\Installer\Http\Requests\SaveEnvironmentRequest::SQLITE_PATH_MAX_LENGTH;
        $databaseConnection = old('database_connection', config('database.default'));
    @endphp
    <form id="environment-form" method="post" action="{{ URL::temporarySignedRoute('installers.environments.store', now()->addMinutes(30)) }}" data-pending-message="{{ trans('packages/installer::ui.installing_database') }}">
        @csrf
        <fieldset>
            <legend>{{ trans('packages/installer::ui.site_details') }}</legend>
            <div class="installer-form-grid">
                @include('packages/installer::partials.field', ['name' => 'app_name', 'label' => trans('packages/installer::ui.labels.app_name'), 'value' => config('app.name'), 'maxlength' => 120])
                @include('packages/installer::partials.field', ['name' => 'app_url', 'label' => trans('packages/installer::ui.labels.app_url'), 'value' => url(''), 'type' => 'url', 'hint' => trans('packages/installer::ui.url_hint')])
            </div>
        </fieldset>
        <fieldset>
            <legend>{{ trans('packages/installer::ui.database_details') }}</legend>
            <p class="installer-section-description">{{ trans('packages/installer::ui.database_hint') }}</p>
            <div class="installer-form-grid">
                <div class="installer-field is-full">
                    <label for="database_connection">{{ trans('packages/installer::ui.labels.database_connection') }} <span class="installer-required" aria-hidden="true">*</span></label>
                    <select id="database_connection" name="database_connection" required data-database-name-maxlength="{{ $databaseNameMaxLength }}" data-sqlite-path-maxlength="{{ $sqlitePathMaxLength }}" @error('database_connection') aria-invalid="true" aria-describedby="database_connection-error" @enderror>
                        @foreach (\Kern\Installer\Enums\DatabaseConnectionsEnum::labels() as $driver => $label)
                            <option value="{{ $driver }}" @selected(old('database_connection', config('database.default')) === $driver)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('database_connection')<p class="installer-field-error" id="database_connection-error">{{ $message }}</p>@enderror
                </div>
                @include('packages/installer::partials.field', ['name' => 'database_hostname', 'label' => trans('packages/installer::ui.labels.database_hostname'), 'value' => config('database.connections.' . config('database.default') . '.host', '127.0.0.1'), 'required' => false, 'maxlength' => 255])
                @include('packages/installer::partials.field', ['name' => 'database_port', 'label' => trans('packages/installer::ui.labels.database_port'), 'value' => config('database.connections.' . config('database.default') . '.port', '5432'), 'required' => false, 'inputmode' => 'numeric'])
                @include('packages/installer::partials.field', ['name' => 'database_name', 'label' => trans('packages/installer::ui.labels.database_name'), 'value' => config('database.connections.' . config('database.default') . '.database'), 'maxlength' => $databaseConnection === 'sqlite' ? $sqlitePathMaxLength : $databaseNameMaxLength, 'class' => 'is-full', 'hint' => trans('packages/installer::ui.sqlite_hint')])
                @include('packages/installer::partials.field', ['name' => 'database_username', 'label' => trans('packages/installer::ui.labels.database_username'), 'value' => config('database.connections.' . config('database.default') . '.username'), 'required' => false, 'maxlength' => 60])
                @include('packages/installer::partials.field', ['name' => 'database_password', 'label' => trans('packages/installer::ui.labels.database_password'), 'type' => 'password', 'required' => false, 'maxlength' => 60])
            </div>
            <details class="installer-help">
                <summary>{{ trans('packages/installer::ui.connection_help') }}</summary>
                <p>{{ trans('packages/installer::ui.host_hint') }}</p>
            </details>
        </fieldset>
    </form>
    <div class="installer-notice">
        <x-core::icon name="ti ti-info-circle" aria-hidden="true" />
        <p>{{ trans('packages/installer::ui.database_notice') }}</p>
    </div>
@endsection
@section('footer')
    <button class="installer-button is-primary" type="submit" form="environment-form">
        {{ trans('packages/installer::ui.install_continue') }} <x-core::icon name="ti ti-arrow-right" aria-hidden="true" />
    </button>
@endsection
