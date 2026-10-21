@extends('layouts.admin')
@section('title', __('Business Workspaces'))

@section('content')
    {{-- One toolbar row: filters on the left. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.workspaces.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business or owner e-mail...') }}" class="form-input search-input w-full">
            </div>
            <div class="w-44 shrink-0">
                <select name="package" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Plan') }}">
                    <option value="">{{ __('All plans') }}</option>
                    @foreach($packages as $package)
                        <option value="{{ $package->key }}" @selected(request('package') === $package->key)>{{ $package->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
            </button>
            <a href="{{ route('admin.workspaces.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Apps on') }}</th>
                        <th>{{ __('Plan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workspaces as $workspace)
                        <tr>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ $workspace->name }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">#{{ $workspace->id }} · {{ $workspace->created_at?->format('M d, Y') }}</span>
                            </td>
                            <td>
                                <span class="text-sm">{{ $workspace->user?->name }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $workspace->user?->email }}</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1 max-w-sm">
                                    @forelse($workspace->workspaceApps->sortBy(fn ($row) => $row->app->sort_order) as $row)
                                        <span class="badge {{ $row->app->edition === \App\Enums\Edition::Full ? 'badge-active' : 'badge-trial' }}">{{ $row->app->name }}</span>
                                    @empty
                                        <span class="text-xs text-[var(--text-muted)]">{{ __('None') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <form action="{{ route('admin.workspaces.package', $workspace->id) }}" method="POST" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="w-40">
                                        <select name="package_id" class="form-select" aria-label="{{ __('Plan for') }} {{ $workspace->name }}">
                                            @foreach($packages as $package)
                                                <option value="{{ $package->id }}" @selected($workspace->package_id === $package->id)>{{ $package->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-xs btn-secondary" data-loading-label="{{ __('Moving...') }}">{{ __('Move') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="building-2" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No business workspaces found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($workspaces->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $workspaces->links() }}
            </div>
        @endif
    </div>

    <p class="text-xs mt-3 text-[var(--text-muted)]">
        {{ __('Moving down turns off what the new plan does not include - a Full app falls back to its Lite edition where the plan has it - and keeps every record. Moving up turns nothing on: the new apps become available in the business\'s Apps Center.') }}
    </p>
@endsection
