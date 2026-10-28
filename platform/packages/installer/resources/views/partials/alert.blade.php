@if (session('message'))
    <div class="installer-notice is-success" role="status">
        <x-core::icon name="ti ti-circle-check" aria-hidden="true" />
        <p>{{ is_array(session('message')) ? session('message')['message'] : session('message') }}</p>
    </div>
@endif
@if ($errors->any())
    <div class="installer-notice is-error" role="alert" tabindex="-1" aria-labelledby="installer-error-title" data-error-summary>
        <x-core::icon name="ti ti-alert-circle" aria-hidden="true" />
        <div>
            <h2 id="installer-error-title">{{ trans('packages/installer::ui.error_title') }}</h2>
            <ul>
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><a href="#{{ $field }}">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
@endif
