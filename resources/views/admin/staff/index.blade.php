@extends('layouts.admin')
@section('title', __('Staff'))

@section('content')
    {{-- One toolbar row: filters on the left, page actions on the right. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.staff.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search staff...') }}" class="form-input search-input w-full">
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
            </button>
            <a href="{{ route('admin.staff.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </form>

        <a href="{{ route('admin.staff.create') }}" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i> {{ __('Add Staff') }}
        </a>
    </div>

    {{-- Staff Table --}}
    <div class="card overflow-visible">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Staff Member') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staff as $member)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $staff->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="user-avatar w-9 h-9">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-[var(--text-primary)]">{{ $member->name }}</span>
                                        <span class="block text-xs text-[var(--text-muted)]">{{ $member->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $member->role->name ?? '—' }}</span>
                            </td>
                            <td>
                                @if($member->status === 'active')
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-inactive">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">
                                {{ $member->created_at->format('M d, Y') }}
                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="{{ route('admin.staff.edit', $member->id) }}" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> {{ __('Edit') }}
                                        </a>
                                        <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('toggle-staff-{{ $member->id }}')">
                                            <i data-lucide="toggle-right" class="w-4 h-4"></i>
                                            {{ $member->status === 'active' ? __('Deactivate') : __('Activate') }}
                                        </button>
                                        @if(auth()->id() !== $member->id)
                                            <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-staff-{{ $member->id }}')" class="text-[var(--danger)]">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> {{ __('Delete') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <x-confirm-modal
                                    id="toggle-staff-{{ $member->id }}"
                                    :title="$member->status === 'active' ? __('Deactivate Staff?') : __('Activate Staff?')"
                                    :message="$member->status === 'active'
                                        ? __('Are you sure you want to deactivate') . ' ' . $member->name . '? ' . __('They will lose admin access.')
                                        : __('Are you sure you want to activate') . ' ' . $member->name . '? ' . __('They will regain admin access.')"
                                    :action="route('admin.staff.toggle-status', $member->id)"
                                    method="PATCH"
                                    :confirm-text="$member->status === 'active' ? __('Deactivate') : __('Activate')"
                                    :confirm-class="$member->status === 'active' ? 'btn-danger' : 'btn-primary'"
                                    :icon="$member->status === 'active' ? 'user-x' : 'user-check'"
                                />

                                @if(auth()->id() !== $member->id)
                                    <x-confirm-modal
                                        id="delete-staff-{{ $member->id }}"
                                        :title="__('Delete Staff?')"
                                        :message="__('Are you sure you want to delete') . ' &quot;' . $member->name . '&quot;? ' . __('This action cannot be undone.')"
                                        :action="route('admin.staff.destroy', $member->id)"
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
                                <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No staff members found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($staff->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $staff->links() }}
            </div>
        @endif
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
