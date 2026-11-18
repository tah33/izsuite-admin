@extends('layouts.admin')
@section('title', __('Announcements'))

{{-- Doc 15 section 15.21: banners for businesses - all, or by plan, country, app or name - or for izSuite's admins, between their days. --}}
@php
    $role   = auth()->user()->role;
    $may    = fn (string $route) => (bool) $role?->hasPermission($route);
    $now    = now();
    $badges = ['scheduled' => 'badge-warning', 'live' => 'badge-success', 'ended' => 'badge-inactive'];
    $tones  = ['info' => 'badge-info', 'important' => 'badge-warning', 'emergency' => 'badge-danger'];
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Announcements') }}</h1>
            <p class="page-subtitle">{{ __('A banner above every page of the workspace for the businesses chosen, or of the admin panel for izSuite\'s admins, between its days.') }}</p>
        </div>
        @if($may('admin.announcements.create'))
            <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Add Announcement') }}</a>
        @endif
    </div>

    <form action="{{ route('admin.announcements.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Title...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-36 shrink-0">
            <select name="state" class="form-select" aria-label="{{ __('State') }}">
                <option value="">{{ __('Any state') }}</option>
                @foreach(\App\Models\Admin\Announcement::STATES as $value => $label)
                    <option value="{{ $value }}" @selected(request('state') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Announcement') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Audience') }}</th>
                        <th>{{ __('Days') }}</th>
                        <th>{{ __('Dismissed') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($announcements as $announcement)
                        @include('admin.announcements._row')
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="megaphone" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No announcements yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($announcements->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $announcements->links() }}</div>
        @endif
    </div>
@endsection
