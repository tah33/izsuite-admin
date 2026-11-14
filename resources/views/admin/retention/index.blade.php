@extends('layouts.admin')
@section('title', __('Data Retention'))

{{-- Doc 15 section 15.24: data-retention rules, as the code keeps and removes data today. Expects $rules and $exportDays. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Data Retention') }}</h1>
        <p class="page-subtitle">{{ __('What izSuite keeps, for how long, and what removes it. Only the rules marked Enforced delete anything; the rest is kept until izSuite sets its retention policy. Closing never deletes, and a legal hold stops deletion.') }}</p>
    </div>

    <div class="card overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Kept') }}</th>
                        <th>{{ __('Removed By') }}</th>
                        <th>{{ __('Rule') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules as $rule)
                        <tr>
                            <td class="text-sm font-medium text-[var(--text-primary)]">{{ __($rule['what']) }}</td>
                            <td class="text-sm">{{ __($rule['kept']) }}</td>
                            <td class="text-sm text-[var(--text-secondary)]">{{ __($rule['removed']) }}</td>
                            <td><span class="badge {{ $rule['enforced'] ? 'badge-success' : 'badge-inactive' }}">{{ $rule['enforced'] ? __('Enforced') : __('Not yet') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($may('admin.retention.update'))
        <form action="{{ route('admin.retention.update') }}" method="POST" class="card max-w-xl">
            @csrf
            @method('PUT')
            <h2 class="section-title mb-2">{{ __('Privacy Request Exports') }}</h2>
            <label class="form-label" for="privacy_export_days">{{ __('Their link works for') }}</label>
            <div class="flex items-center gap-2">
                <input type="number" id="privacy_export_days" name="privacy_export_days" class="form-input w-28" min="1" max="90" required value="{{ old('privacy_export_days', $exportDays) }}">
                <span class="text-sm text-[var(--text-secondary)]">{{ __('days; then the file is deleted.') }}</span>
            </div>
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('For exports made from now. One already sent keeps its day.') }}</p>
            <button type="submit" class="btn btn-primary mt-4"><i data-lucide="save" class="w-4 h-4"></i>{{ __('Save') }}</button>
        </form>
    @endif
@endsection
