@extends('layouts.admin')
@section('title', __('Usage'))

{{-- Doc 15 section 15.18: each business's users, active locations, storage and apps against its plan's limits, and where it stands. --}}
@php
    $count = fn ($amount) => number_format((int) $amount);
    $bytes = fn ($amount) => \App\Support\Bytes::format((int) $amount);
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Usage') }}</h1>
        <p class="page-subtitle">{{ __('Each business\'s users, active locations, storage and apps against its plan\'s limits. A limit stops a business adding more; it never removes anything.') }}</p>
    </div>

    @include('admin.usage._filters')

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Locations') }}</th>
                        <th>{{ __('Storage') }}</th>
                        <th>{{ __('Apps on') }}</th>
                        <th>{{ __('State') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.usage.show', $row['business']->id) }}" class="font-semibold text-[var(--primary)]">{{ $row['business']->name }}</a>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $row['business']->businessId() }} · {{ $row['business']->plan_name ?? __('No plan') }}</span>
                            </td>
                            <td>@include('admin.usage._cell', ['line' => $row['usage']['users'], 'format' => $count])</td>
                            <td>@include('admin.usage._cell', ['line' => $row['usage']['locations'], 'format' => $count])</td>
                            <td>@include('admin.usage._cell', ['line' => $row['usage']['storage'], 'format' => $bytes])</td>
                            <td class="text-sm whitespace-nowrap">
                                {{ $row['usage']['apps']['used'] }}
                                <span class="block text-xs text-[var(--text-muted)]">{{ __(':count on its plan', ['count' => $row['usage']['apps']['plan']]) }}</span>
                            </td>
                            <td class="whitespace-nowrap">@include('admin.usage._state', ['state' => $row['usage']['state']])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="gauge" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No businesses match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
