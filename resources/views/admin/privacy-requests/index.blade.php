@extends('layouts.admin')
@section('title', __('Privacy Requests'))

{{-- Doc 15 section 15.24: data exports, account closures and corrections - each worked once the person asking is checked. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
    $Privacy = \App\Models\Admin\PrivacyRequest::class;
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Privacy Requests') }}</h1>
            <p class="page-subtitle">{{ __('A business\'s data exported, an account closed or data corrected, as someone asked. Each is worked only once the person asking is checked, and answered within :days days.', ['days' => $Privacy::DUE_DAYS]) }}</p>
        </div>
        @if($may('admin.privacy-requests.create'))
            <a href="{{ route('admin.privacy-requests.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Log a Request') }}</a>
        @endif
    </div>

    <form action="{{ route('admin.privacy-requests.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name, e-mail or PR- number...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-40 shrink-0">
            <select name="type" class="form-select" aria-label="{{ __('Type') }}">
                <option value="">{{ __('Any type') }}</option>
                @foreach($Privacy::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-44 shrink-0">
            <select name="status" class="form-select" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Any status') }}</option>
                <option value="open" @selected(request('status') === 'open')>{{ __('Still open') }}</option>
                @foreach($Privacy::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.privacy-requests.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Request') }}</th>
                        <th>{{ __('Asked by') }}</th>
                        <th>{{ __('About') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $privacy)
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold text-[var(--text-primary)]">{{ $privacy->number() }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ __($Privacy::TYPES[$privacy->type]) }}</span>
                            </td>
                            <td class="text-sm">
                                {{ $privacy->requester_name }}
                                <span class="block text-xs text-[var(--text-muted)]">{{ $privacy->requester_email }}</span>
                            </td>
                            <td class="text-sm">
                                @if($privacy->workspace)
                                    <span class="block">{{ $privacy->workspace->name }} <span class="text-xs text-[var(--text-muted)]">{{ $privacy->workspace->businessId() }}</span></span>
                                @endif
                                @if($privacy->user)
                                    <span class="block text-xs text-[var(--text-muted)]">{{ $privacy->user->email }}</span>
                                @endif
                            </td>
                            <td>@include('admin.privacy-requests._status')</td>
                            <td class="text-sm whitespace-nowrap">{{ $privacy->isOpen() ? $privacy->due_on->format('M d, Y') : '—' }}</td>
                            <td class="text-end">
                                @if($may('admin.privacy-requests.show'))
                                    <a href="{{ route('admin.privacy-requests.show', $privacy->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Open') }}"><i data-lucide="eye" class="w-3.5 h-3.5"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="shield-check" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No privacy requests here.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $requests->links() }}</div>
        @endif
    </div>
@endsection
