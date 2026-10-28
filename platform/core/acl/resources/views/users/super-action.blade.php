<form method="POST" action="{{ route($revoke ? 'users.remove-super' : 'users.make-super', $user) }}" class="d-inline">
    @csrf
    @if ($revoke)
        @method('DELETE')
    @endif
    <button type="submit" class="btn btn-sm {{ $revoke ? 'btn-warning' : 'btn-success' }}">{{ trans($revoke ? 'core/acl::users.remove_super' : 'core/acl::users.make_super') }}</button>
</form>
