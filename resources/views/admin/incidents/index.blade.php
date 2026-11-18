@extends('layouts.admin')
@section('title', __('Incidents'))

{{-- Doc 15 section 15.23: what went wrong with a service, who is on it, and how it stands - those not resolved first. --}}
@php
    $role     = auth()->user()->role;
    $may      = fn (string $route) => (bool) $role?->hasPermission($route);
    $services = \App\Support\ServiceStatus::NAMES;
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Incidents') }}</h1>
            <p class="page-subtitle">{{ __('Something wrong with a service, from when it started until it is resolved: who is responsible, and what was found and done.') }}</p>
        </div>
        @if($may('admin.incidents.create'))
            <a href="{{ route('admin.incidents.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Open Incident') }}</a>
        @endif
    </div>

    <form action="{{ route('admin.incidents.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Words of the title...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-44 shrink-0">
            <select name="status" class="form-select" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Any status') }}</option>
                <option value="unresolved" @selected(request('status') === 'unresolved')>{{ __('Not resolved') }}</option>
                @foreach(\App\Models\Admin\Incident::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-48 shrink-0">
            <select name="service" class="form-select" aria-label="{{ __('Service') }}">
                <option value="">{{ __('Any service') }}</option>
                @foreach($services as $value => $label)
                    <option value="{{ $value }}" @selected(request('service') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-36 shrink-0">
            <select name="severity" class="form-select" aria-label="{{ __('Severity') }}">
                <option value="">{{ __('Any severity') }}</option>
                @foreach(\App\Models\Admin\Incident::SEVERITIES as $value => $label)
                    <option value="{{ $value }}" @selected(request('severity') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.incidents.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Incident') }}</th>
                        <th>{{ __('Severity and Status') }}</th>
                        <th>{{ __('Responsible') }}</th>
                        <th>{{ __('Started') }}</th>
                        <th>{{ __('Resolved') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $incident)
                        <tr>
                            <td>
                                <a href="{{ route('admin.incidents.show', $incident->id) }}" class="font-semibold text-[var(--primary)]">{{ $incident->title }}</a>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $incident->service ? __($services[$incident->service] ?? $incident->service) : __('No one service') }}</span>
                            </td>
                            <td class="whitespace-nowrap">@include('admin.incidents._badges')</td>
                            <td class="text-sm">{{ $incident->responsible?->name ?? __('Nobody yet') }}</td>
                            <td class="text-sm whitespace-nowrap">{{ $incident->started_at->format('M d, Y H:i') }}</td>
                            <td class="text-sm whitespace-nowrap">{{ $incident->resolved_at?->format('M d, Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="siren" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No incidents.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($incidents->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $incidents->links() }}</div>
        @endif
    </div>
@endsection
