@extends('layouts.admin')
@section('title', __('Departments'))

@section('content')
    {{-- One toolbar row: filters on the left, page actions on the right. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.departments.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="w-full sm:w-80">
                <select name="recruiter_id" id="recruiter_id" class="form-input" onchange="this.form.submit()" aria-label="{{ __('Filter by Recruiter') }}">
                    <option value="">{{ __('All Recruiters') }}</option>
                    @foreach($recruiters as $recruiter)
                        <option value="{{ $recruiter->id }}" {{ request('recruiter_id') == $recruiter->id ? 'selected' : '' }}>
                            {{ $recruiter->name }} ({{ $recruiter->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            @if(request('recruiter_id'))
                <a href="{{ route('admin.departments.index') }}" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i> {{ __('Clear') }}
                </a>
            @endif
        </form>

        <a href="{{ route('admin.departments.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> {{ __('New Department') }}
        </a>
    </div>

    {{-- Departments Table --}}
    <div class="card overflow-visible">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Department Name') }}</th>
                        <th>{{ __('Created By') }}</th>
                        <th>{{ __('Created At') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $loop->iteration + ($departments->currentPage() - 1) * $departments->perPage() }}</td>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ $department->name }}</span>
                            </td>
                            <td>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">{{ $department->creator?->name ?? __('System') }}</span>
                                    <span class="text-xs text-[var(--text-muted)]">{{ $department->creator?->email }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm text-[var(--text-secondary)]">{{ $department->created_at->format('M d, Y') }}</span>
                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="{{ route('admin.departments.edit', $department->id) }}" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> {{ __('Edit') }}
                                        </a>
                                        <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-dept-{{ $department->id }}')">
                                            <i data-lucide="trash-2" class="w-4 h-4 text-[var(--danger)]"></i> <span class="text-[var(--danger)]">{{ __('Delete') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <x-confirm-modal
                                    id="delete-dept-{{ $department->id }}"
                                    :title="__('Delete Department?')"
                                    :message="__('Are you sure you want to delete this department?') . ' ' . __('This action cannot be undone.')"
                                    :action="route('admin.departments.destroy', $department->id)"
                                    method="DELETE"
                                    :confirm-text="__('Delete')"
                                    confirm-class="btn-danger"
                                    icon="trash-2"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="layers" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No departments found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($departments->hasPages())
            <div class="p-4 border-t border-[var(--border-color)]">
                {{ $departments->links() }}
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
