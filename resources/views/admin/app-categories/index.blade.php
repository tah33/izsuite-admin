@extends('layouts.admin')
@section('title', __('App Categories'))

@section('content')
    {{-- One toolbar row: filters on the left, page actions on the right. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.app-categories.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search categories...') }}" class="form-input search-input w-full">
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
            </button>
            <a href="{{ route('admin.app-categories.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </form>

        <a href="{{ route('admin.app-categories.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Add Category') }}
        </a>
    </div>

    {{-- App Categories Table --}}
    <div class="card overflow-visible">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Category Name') }}</th>
                        <th>{{ __('Apps') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $category->sort_order }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="{{ $category->icon ?: 'folder' }}" class="w-4 h-4 text-[var(--text-muted)]"></i>
                                    <span class="font-semibold text-[var(--text-primary)]">{{ $category->name }}</span>
                                </div>
                            </td>
                            <td class="text-sm">{{ $category->apps_count ?? 0 }}</td>
                            <td>
                                <form action="{{ route('admin.app-categories.toggle-status', $category->id) }}" method="POST" class="inline-block" onchange="this.request ? this.requestSubmit() : this.submit()">
                                    @csrf
                                    @method('PATCH')
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" class="toggle-switch" {{ $category->is_active ? 'checked' : '' }} onchange="this.form.submit()">
                                    </label>
                                </form>
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">
                                {{ $category->created_at->format('M d, Y') }}
                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="{{ route('admin.app-categories.edit', $category->id) }}" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> {{ __('Edit') }}
                                        </a>
                                        <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-category-{{ $category->id }}')">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i> {{ __('Delete') }}
                                        </button>
                                    </div>
                                </div>

                                <x-confirm-modal
                                    id="delete-category-{{ $category->id }}"
                                    :title="__('Delete Category?')"
                                    :message="__('Are you sure you want to delete') . ' &quot;' . $category->name . '&quot;? ' . __('This action cannot be undone.')"
                                    :action="route('admin.app-categories.destroy', $category->id)"
                                    method="DELETE"
                                    :confirm-text="__('Delete')"
                                    confirm-class="btn-danger"
                                    icon="trash-2"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="tags" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No app categories found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $categories->links() }}
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
