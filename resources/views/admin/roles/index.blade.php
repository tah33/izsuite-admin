@extends('layouts.admin')
@section('title', __('Roles'))

@section('content')
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> {{ __('New Role') }}
        </a>
    </div>

    {{-- Roles Table --}}
    <div class="card overflow-visible">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Slug') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Permissions') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $loop->iteration }}</td>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ $role->name }}</span>
                            </td>
                            <td>
                                <code class="text-xs px-2 py-0.5 rounded bg-[var(--content-bg)] text-[var(--text-muted)]">{{ $role->slug }}</code>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $role->users_count }}</span>
                            </td>
                            <td>
                                @if($role->permissions && count($role->permissions) > 0)
                                    <span class="text-sm text-[var(--text-secondary)]">{{ __(':count permissions', ['count' => count($role->permissions)]) }}</span>
                                @else
                                    <span class="text-sm text-[var(--text-muted)]">{{ __('None') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="{{ route('admin.roles.edit', $role->id) }}" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> {{ __('Edit') }}
                                        </a>
                                        @if($role->users_count === 0)
                                            <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-role-{{ $role->id }}')" class="text-[var(--danger)]">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> {{ __('Delete') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if($role->users_count === 0)
                                    <x-confirm-modal
                                        id="delete-role-{{ $role->id }}"
                                        :title="__('Delete Role?')"
                                        :message="__('Are you sure you want to delete the') . ' &quot;' . $role->name . '&quot; ' . __('role?') . ' ' . __('This action cannot be undone.')"
                                        :action="route('admin.roles.destroy', $role->id)"
                                        method="DELETE"
                                        :confirm-text="__('Delete')"
                                        confirm-class="btn-danger"
                                        icon="trash-2"
                                    />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="shield" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No roles found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function toggleDropdown(btn) {
        var dropdown = btn.nextElementSibling;
        document.querySelectorAll('.action-dropdown.show').forEach(function(dd) {
            if (dd !== dropdown) dd.classList.remove('show');
        });
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            var rect = btn.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = (rect.bottom + 4) + 'px';
            dropdown.style.right = (window.innerWidth - rect.right) + 'px';
            dropdown.style.left = 'auto';
            var ddRect = dropdown.getBoundingClientRect();
            if (ddRect.bottom > window.innerHeight) {
                dropdown.style.top = (rect.top - ddRect.height - 4) + 'px';
            }
        }
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.action-dropdown-wrapper')) {
            document.querySelectorAll('.action-dropdown.show').forEach(function(dd) {
                dd.classList.remove('show');
            });
        }
    });
</script>
@endpush
