@extends('packages/installer::layouts.master')

@section('pageTitle', trans('packages/installer::ui.steps.welcome'))
@section('header')
    <h1>{{ trans('packages/installer::ui.welcome_title') }}</h1>
    <p>{{ trans('packages/installer::ui.welcome_description') }}</p>
@endsection
@section('content')
    <form method="post" action="{{ route('installers.welcome.next') }}" id="welcome-form" data-pending-message="{{ trans('packages/installer::ui.preparing') }}">
        @csrf
        <div class="installer-field">
            <label for="language">{{ trans('packages/installer::ui.labels.language') }}</label>
            <select name="language" id="language" aria-describedby="language-hint{{ $errors->has('language') ? ' language-error' : '' }}" @error('language') aria-invalid="true" @enderror>
                @foreach ($languages as $code => $language)
                    <option value="{{ $code }}" @selected(old('language', app()->getLocale()) === $code)>{{ $language }}</option>
                @endforeach
            </select>
            <p id="language-hint" class="installer-hint">{{ trans('packages/installer::ui.language_hint') }}</p>
            @error('language')<p class="installer-field-error" id="language-error">{{ $message }}</p>@enderror
        </div>
    </form>
    <section class="installer-preparation">
        <h2>{{ trans('packages/installer::ui.before_start') }}</h2>
        <ul class="installer-checklist">
            <li><x-core::icon name="ti ti-database" aria-hidden="true" /><span>{{ trans('packages/installer::ui.prepare_database') }}</span></li>
            <li><x-core::icon name="ti ti-key" aria-hidden="true" /><span>{{ trans('packages/installer::ui.prepare_credentials') }}</span></li>
            <li><x-core::icon name="ti ti-user" aria-hidden="true" /><span>{{ trans('packages/installer::ui.prepare_account') }}</span></li>
        </ul>
    </section>
@endsection
@section('footer')
    <button class="installer-button is-primary" type="submit" form="welcome-form">
        {{ trans('packages/installer::ui.start_setup') }} <x-core::icon name="ti ti-arrow-right" aria-hidden="true" />
    </button>
@endsection
