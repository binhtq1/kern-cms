@extends('packages/installer::layouts.master')

@section('pageTitle', trans('packages/installer::ui.finished_title'))
@section('header-icon')
    <span class="installer-finished-icon"><x-core::icon name="ti ti-check" aria-hidden="true" /></span>
@endsection
@section('header')
    <h1>{{ trans('packages/installer::ui.finished_title') }}</h1>
    <p>{{ trans('packages/installer::ui.finished_ready') }}</p>
@endsection
@section('content')
    <div class="installer-finished">
        <h2>{{ trans('packages/installer::ui.next_title') }}</h2>
        <p>{{ trans('packages/installer::ui.finished_description') }}</p>
    </div>
@endsection
@section('footer')
    <a class="installer-button is-primary" href="{{ route('access.login') }}">
        {{ trans('packages/installer::ui.open_admin') }} <x-core::icon name="ti ti-arrow-right" aria-hidden="true" />
    </a>
@endsection
