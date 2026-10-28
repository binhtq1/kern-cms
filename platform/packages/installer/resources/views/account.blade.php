@extends('packages/installer::layouts.master')

@section('pageTitle', trans('packages/installer::ui.steps.account'))
@section('header')
    <h1>{{ trans('packages/installer::ui.account_title') }}</h1>
    <p>{{ trans('packages/installer::ui.account_description') }}</p>
@endsection
@section('content')
    <form id="create-account-form" method="post" action="{{ URL::temporarySignedRoute('installers.accounts.store', now()->addMinutes(30)) }}" data-pending-message="{{ trans('packages/installer::ui.creating_account') }}">
        @csrf
        <div class="installer-form-grid">
            @include('packages/installer::partials.field', ['name' => 'first_name', 'label' => trans('packages/installer::ui.labels.first_name'), 'autocomplete' => 'given-name', 'minlength' => 2, 'maxlength' => 60])
            @include('packages/installer::partials.field', ['name' => 'last_name', 'label' => trans('packages/installer::ui.labels.last_name'), 'autocomplete' => 'family-name', 'minlength' => 2, 'maxlength' => 60])
            @include('packages/installer::partials.field', ['name' => 'username', 'label' => trans('packages/installer::ui.labels.username'), 'autocomplete' => 'username', 'minlength' => 4, 'maxlength' => 30, 'hint' => trans('packages/installer::ui.username_hint')])
            @include('packages/installer::partials.field', ['name' => 'email', 'label' => trans('packages/installer::ui.labels.email'), 'type' => 'email', 'autocomplete' => 'email', 'minlength' => 6, 'maxlength' => 60])
            @include('packages/installer::partials.field', ['name' => 'password', 'label' => trans('packages/installer::ui.labels.password'), 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 6, 'hint' => trans('packages/installer::ui.password_hint')])
            @include('packages/installer::partials.field', ['name' => 'password_confirmation', 'label' => trans('packages/installer::ui.labels.password_confirmation'), 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 6])
        </div>
    </form>
    <div class="installer-notice">
        <x-core::icon name="ti ti-shield-lock" aria-hidden="true" />
        <p>{{ trans('packages/installer::ui.account_notice') }}</p>
    </div>
@endsection
@section('footer')
    <button class="installer-button is-primary" type="submit" form="create-account-form">
        {{ trans('packages/installer::ui.create_account') }} <x-core::icon name="ti ti-arrow-right" aria-hidden="true" />
    </button>
@endsection
