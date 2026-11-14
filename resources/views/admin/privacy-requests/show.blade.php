@extends('layouts.admin')
@section('title', $request->number())

{{-- One privacy request (Doc 15 section 15.24): what was asked, how the asker was checked, and what was done. Expects $request, $businesses and $exportDays. --}}
@php
    $role    = auth()->user()->role;
    $may     = fn (string $route) => (bool) $role?->hasPermission($route);
    $Privacy = \App\Models\Admin\PrivacyRequest::class;
    $privacy = $request;
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.privacy-requests.index') }}" class="text-[var(--primary)]">{{ __('Privacy Requests') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $privacy->number() }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $privacy->number() }} · {{ __($Privacy::TYPES[$privacy->type]) }}</h1>
            @include('admin.privacy-requests._status')
        </div>
        <p class="page-subtitle">{{ $privacy->isOpen() ? __('Due on :date.', ['date' => $privacy->due_on->format('M d, Y')]) : __('Answered on :date.', ['date' => $privacy->completed_at?->format('M d, Y')]) }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @include('admin.privacy-requests._details')
        </div>
        <div class="space-y-6">
            @include('admin.privacy-requests._actions')
        </div>
    </div>
@endsection
