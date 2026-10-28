@php
    $nativeValidationConfig = [
        'selector' => $validator['selector'],
        'rules' => $validator['rules'],
        'focusOnError' => (bool) config('core.js-validation.js-validation.focus_on_error'),
    ];

    if (isset($validator['ignore']) && is_string($validator['ignore'])) {
        $nativeValidationConfig['ignore'] = $validator['ignore'];
    }
@endphp
<script type="application/json" data-kern-validation>{!! json_encode($nativeValidationConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
