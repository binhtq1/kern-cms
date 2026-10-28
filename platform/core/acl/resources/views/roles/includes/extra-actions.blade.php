<x-core::button type="submit" :formaction="route('roles.duplicate', $role->getKey())" formmethod="POST" formnovalidate icon="ti ti-copy">
    {{ trans('core/acl::permissions.duplicate') }}
</x-core::button>
