@extends('packages/installer::layouts.master')

@section('pageTitle', trans('packages/installer::ui.steps.theme'))
@section('header')
    <h1>{{ trans('packages/installer::ui.theme_title') }}</h1>
    <p>{{ trans('packages/installer::ui.theme_description') }}</p>
@endsection
@section('content')
    <form action="{{ URL::temporarySignedRoute('installers.themes.store', now()->addMinutes(30)) }}" method="post" id="choose-theme-form" data-pending-message="{{ trans('packages/installer::ui.applying_theme') }}">
        @csrf
        <fieldset id="theme" @error('theme') aria-describedby="theme-error" @enderror>
            <legend class="installer-sr-only">{{ trans('packages/installer::ui.theme_title') }}</legend>
            <div class="installer-themes">
                @foreach ($themes as $name => $theme)
                    <label class="installer-theme">
                        <input type="radio" name="theme" value="{{ $name }}" aria-labelledby="theme-{{ $loop->index }}-label" @checked(old('theme', array_key_first($themes)) === $name) required>
                        <span class="installer-theme-preview">
                            <img src="{{ $theme['image'] }}" alt="" loading="{{ $loop->first ? 'eager' : 'lazy' }}" width="480" height="300">
                            <span class="installer-theme-fallback" hidden><x-core::icon name="ti ti-photo" aria-hidden="true" />{{ trans('packages/installer::ui.preview_unavailable') }}</span>
                        </span>
                        <span class="installer-theme-caption">
                            <strong id="theme-{{ $loop->index }}-label">{{ $theme['label'] }}</strong>
                            <span class="installer-theme-description">{{ trans('packages/installer::ui.theme_card_description') }}</span>
                            <span class="installer-theme-selected"><x-core::icon name="ti ti-circle-check" aria-hidden="true" />{{ trans('packages/installer::ui.selected') }}</span>
                            <span class="installer-theme-select">{{ trans('packages/installer::ui.select_theme') }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('theme')<p class="installer-field-error" id="theme-error">{{ $message }}</p>@enderror
        </fieldset>
    </form>
    <div class="installer-notice">
        <x-core::icon name="ti ti-info-circle" aria-hidden="true" />
        <p>{{ trans('packages/installer::ui.theme_notice') }}</p>
    </div>
@endsection
@section('footer')
    <button class="installer-button is-primary" type="submit" form="choose-theme-form">
        {{ trans('packages/installer::ui.continue') }} <x-core::icon name="ti ti-arrow-right" aria-hidden="true" />
    </button>
@endsection
