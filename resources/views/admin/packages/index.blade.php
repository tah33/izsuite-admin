@extends('layouts.admin')
@section('title', __('Packages'))

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <p class="text-sm flex-1 min-w-[240px] text-[var(--text-secondary)]">
            {{ __('The plans a business can be on. A higher plan includes everything a lower one does. Which apps each plan includes is set in Package Access; a business turns them on itself, in the Apps Center.') }}
        </p>
        <a href="{{ route('admin.package-access.index') }}" class="btn btn-primary">
            <i data-lucide="grid-3x3" class="w-4 h-4"></i> {{ __('Package Access') }}
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Rank') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Key') }}</th>
                        <th>{{ __('Apps') }}</th>
                        <th>{{ __('Storage') }}</th>
                        <th>{{ __('Businesses') }}</th>
                        <th>{{ __('Offered') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $package)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $package->rank }}</td>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ $package->name }}</span>
                                @if($package->description)
                                    <span class="block text-xs text-[var(--text-muted)] max-w-md">{{ $package->description }}</span>
                                @endif
                            </td>
                            <td><code class="text-xs">{{ $package->key }}</code></td>
                            <td class="text-sm">{{ $package->apps_count }}</td>
                            <td class="text-sm">
                                @if($package->storage_limit_mb === null)
                                    <span class="text-[var(--text-muted)]">{{ __('No limit') }}</span>
                                @else
                                    {{ \App\Support\Bytes::format($package->storage_limit_mb * 1024 * 1024) }}
                                @endif
                            </td>
                            <td class="text-sm">{{ $package->workspaces_count }}</td>
                            <td>
                                @if($package->is_active)
                                    <span class="badge badge-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-inactive">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.packages.edit', $package->id) }}" class="btn btn-xs btn-secondary">
                                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i> {{ __('Edit') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
