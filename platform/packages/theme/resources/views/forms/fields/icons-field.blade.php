@php
    Arr::set($attributes, 'class', Arr::get($attributes, 'class') . ' icon-select');
    Arr::set($attributes, 'data-empty-value', __('-- None --'));
    Arr::set($attributes, 'data-check-initialized', true);
@endphp

{!! Form::customSelect($name, [$value => $value], $value, $attributes) !!}

@once
    @if (request()->ajax())
        @include('packages/theme::forms.fields.includes.icon-fields-script')

    @else
        @include('packages/theme::forms.fields.includes.icon-fields-script')

    @endif
@endonce
