@php
    $type = $type ?? 'text';
    $hint = $hint ?? null;
    $error = $errors->first($name);
    $required = $required ?? true;
    $conditional = in_array($name, ['database_hostname', 'database_port', 'database_username']);
    $descriptions = array_filter([$hint ? $name . '-hint' : null, $error ? $name . '-error' : null]);
@endphp
<div @class(['installer-field', $class ?? ''])>
    <label for="{{ $name }}">
        <span data-field-label-text>{{ $label }}</span> <span class="installer-required" aria-hidden="true" @unless($required) hidden @endunless>*</span>
        @if (!$required && !$conditional)<span class="installer-optional">{{ trans('packages/installer::ui.optional') }}</span>@endif
    </label>
    <div @class(['installer-input-wrap', 'has-password' => $type === 'password'])>
        <input
            id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            value="{{ $type === 'password' ? '' : old($name, $value ?? '') }}"
            autocomplete="{{ $autocomplete ?? 'off' }}"
            @if($required) required @endif
            @isset($minlength) minlength="{{ $minlength }}" @endisset
            @isset($maxlength) maxlength="{{ $maxlength }}" @endisset
            @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
            @if(!in_array($name, ['first_name', 'last_name', 'app_name'])) autocapitalize="none" spellcheck="false" @endif
            @if($error) aria-invalid="true" @endif
            @if($descriptions) aria-describedby="{{ implode(' ', $descriptions) }}" @endif
        >
        @if ($type === 'password')
            <button type="button" class="installer-password-toggle" hidden data-password-toggle="{{ $name }}" data-field-label="{{ $label }}" aria-controls="{{ $name }}" aria-pressed="false" aria-label="{{ trans('packages/installer::ui.show_password_for', ['field' => $label]) }}" title="{{ trans('packages/installer::ui.show_password_for', ['field' => $label]) }}">
                <x-core::icon name="ti ti-eye" class="installer-eye" aria-hidden="true" />
                <x-core::icon name="ti ti-eye-off" class="installer-eye-off" aria-hidden="true" />
            </button>
        @endif
    </div>
    @if($hint)<p class="installer-hint" id="{{ $name }}-hint">{{ $hint }}</p>@endif
    @if($error)<p class="installer-field-error" id="{{ $name }}-error">{{ $error }}</p>@endif
</div>
