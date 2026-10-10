@extends('layouts.admin')
@section('title', __('Apps'))

@section('content')
    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.apps.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Add App') }}
        </a>
    </div>

    <form action="{{ route('admin.apps.index') }}" method="GET" class="card mb-3">
        <div class="flex flex-col md:flex-row md:items-end gap-3 w-full">
            <div class="search-input-wrapper flex-1 min-w-0 ![max-width:none]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search apps...') }}" class="form-input search-input w-full">
            </div>
            <div class="md:w-[140px] shrink-0">
                <select name="per_page" class="form-select w-full" onchange="this.form.submit()">
                    @foreach([10, 15, 25, 50, 100] as $option)
                        <option value="{{ $option }}" {{ (int) request('per_page', 15) === $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4 me-1"></i>{{ __('Filter') }}
                </button>
                <a href="{{ route('admin.apps.index') }}" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </form>

    {{-- Apps Table --}}
    <div class="card overflow-visible">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('App') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($apps as $app)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $apps->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if($app->logo_url)
                                        <img src="{{ asset('storage/'.$app->logo_url) }}" alt="{{ $app->name }}" class="w-9 h-9 rounded-lg object-cover shrink-0">
                                    @else
                                        <div class="user-avatar w-9 h-9">
                                            {{ strtoupper(substr($app->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <span class="font-semibold text-[var(--text-primary)]">{{ $app->name }}</span>
                                        @if($app->description)
                                            <span class="block text-xs text-[var(--text-muted)] line-clamp-1">{{ \Illuminate\Support\Str::limit($app->description, 50) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($app->category)
                                    <span class="badge badge-info">{{ $app->category }}</span>
                                @else
                                    <span class="text-[var(--text-muted)]">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-sm font-medium">{{ number_format((float) $app->price, 2) }}</span>
                            </td>
                            <td>
                                @php
                                    $statusBadge = [
                                        'included'    => 'badge-success',
                                        'active'      => 'badge-active',
                                        'available'   => 'badge-trial',
                                        'locked'      => 'badge-cancelled',
                                        'coming_soon' => 'badge-warning',
                                    ][$app->status] ?? 'badge-inactive';
                                @endphp
                                <span class="badge {{ $statusBadge }}">{{ __(\App\Models\Frontend\Application::STATUSES[$app->status] ?? ucfirst((string) $app->status)) }}</span>
                            </td>
                            <td>
                                @if($app->is_active)
                                    <span class="badge badge-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-inactive">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">
                                {{ $app->created_at->format('M d, Y') }}
                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="{{ route('admin.apps.edit', $app->id) }}" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> {{ __('Edit') }}
                                        </a>
                                        <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-app-{{ $app->id }}')">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i> {{ __('Delete') }}
                                        </button>
                                    </div>
                                </div>

                                <x-confirm-modal
                                    id="delete-app-{{ $app->id }}"
                                    :title="__('Delete App?')"
                                    :message="__('Are you sure you want to delete') . ' &quot;' . $app->name . '&quot;? ' . __('This action cannot be undone.')"
                                    :action="route('admin.apps.destroy', $app->id)"
                                    method="DELETE"
                                    :confirm-text="__('Delete')"
                                    confirm-class="btn-danger"
                                    icon="trash-2"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="app-window" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No apps found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($apps->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $apps->links() }}
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
