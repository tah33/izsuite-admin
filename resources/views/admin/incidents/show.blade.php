@extends('layouts.admin')
@section('title', $incident->title)

{{-- One incident (Doc 15 section 15.23): what it is, who is responsible, and its timeline - a note moving it on. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.incidents.index') }}" class="text-[var(--primary)]">{{ __('Incidents') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $incident->title }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $incident->title }}</h1>
            @include('admin.incidents._badges')
            @if($may('admin.incidents.edit'))
                <a href="{{ route('admin.incidents.edit', $incident->id) }}" class="btn btn-secondary ms-auto"><i data-lucide="edit-2" class="w-4 h-4"></i>{{ __('Edit') }}</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="card lg:col-span-2">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                @foreach([
                    __('Service')     => $incident->service ? __(\App\Support\ServiceStatus::NAMES[$incident->service] ?? $incident->service) : __('No one service'),
                    __('Responsible') => $incident->responsible?->name ?? __('Nobody yet'),
                    __('Started')     => $incident->started_at->format('M d, Y H:i').' UTC',
                    __('Resolved')    => $incident->resolved_at ? $incident->resolved_at->format('M d, Y H:i').' UTC · '.$incident->started_at->diffForHumans($incident->resolved_at, true).' '.__('after it started') : __('Not yet'),
                    __('Opened by')   => ($incident->creator?->name ?? '-').' · '.$incident->created_at->format('M d, Y H:i'),
                ] as $label => $value)
                    <div>
                        <dt class="text-[var(--text-muted)]">{{ $label }}</dt>
                        <dd class="font-medium text-[var(--text-primary)]">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if($incident->summary)
                <p class="mt-4 text-sm whitespace-pre-line text-[var(--text-secondary)]">{{ $incident->summary }}</p>
            @endif
        </div>

        @if($may('admin.incidents.note'))
            @include('admin.incidents._note-form')
        @endif
    </div>

    @include('admin.incidents._timeline')
@endsection
