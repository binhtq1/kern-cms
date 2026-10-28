<script type="text/javascript">
    var KernVariables = KernVariables || {};

    @if (Auth::guard()->check())
        KernVariables.languages = {
            tables: {{ Js::from(trans('core/base::tables')) }},
            notices_msg: {{ Js::from(trans('core/base::notices')) }},
            pagination: {{ Js::from(trans('pagination')) }},
        };

        KernVariables.menu_item_count_url = "{{ route('menu-items-count') }}";
    @else
        KernVariables.languages = {
            notices_msg: {{ Js::from(trans('core/base::notices')) }},
        };
    @endif
</script>

@push('footer')
    @if (Session::has('success_msg') || Session::has('error_msg') || (isset($errors) && $errors->any()) || isset($error_msg))
        <script type="text/javascript">
            $(function() {
                @if (Session::has('success_msg'))
                    Kern.showSuccess('{!! BaseHelper::cleanToastMessage(session('success_msg')) !!}');
                @endif
                @if (Session::has('error_msg'))
                    Kern.showError('{!! BaseHelper::cleanToastMessage(session('error_msg')) !!}');
                @endif
                @if (isset($error_msg))
                    Kern.showError('{!! BaseHelper::cleanToastMessage($error_msg) !!}');
                @endif
                @if (isset($errors))
                    @foreach ($errors->all() as $error)
                        Kern.showError('{!! BaseHelper::cleanToastMessage($error) !!}');
                    @endforeach
                @endif
            })
        </script>
    @endif
@endpush
