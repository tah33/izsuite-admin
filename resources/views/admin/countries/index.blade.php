@extends('layouts.admin')
@section('title', __('Countries'))

{{-- Doc 15 section 15.19: the countries, and the defaults a new business in each takes - by version from a day. --}}
@php
    $role    = auth()->user()->role;
    $may     = fn (string $route) => (bool) $role?->hasPermission($route);
    $numbers = \App\Models\User\DocumentTemplate::NUMBER_FORMAT_EXAMPLES;
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Countries') }}</h1>
        <p class="page-subtitle">{{ __('The defaults a new business in each country starts from: its currency, time zone, regional settings and how it writes an address. Businesses already set up keep their own settings, and documents already issued keep what they printed.') }}</p>
    </div>

    <form action="{{ route('admin.countries.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Name or code...') }}" class="form-input search-input w-full">
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Search') }}</button>
        <a href="{{ route('admin.countries.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Country') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th>{{ __('Time Zone') }}</th>
                        <th>{{ __('Dates and Numbers') }}</th>
                        <th>{{ __('Businesses') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($countries as $country)
                        @php $waiting = $country->versions->first(); @endphp
                        <tr>
                            <td class="min-w-[200px]">
                                <span class="font-semibold text-[var(--text-primary)]">{{ $country->name }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $country->code }}{{ $country->calling_code ? ' · +'.$country->calling_code : '' }}</span>
                                @if($waiting)
                                    <span class="badge badge-warning mt-1">{{ __('Version :number from :date', ['number' => $waiting->number, 'date' => $waiting->starts_on->format('M d, Y')]) }}</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $country->currency ?? '—' }}</td>
                            <td class="text-sm whitespace-nowrap">{{ $country->timezone ?? '—' }}</td>
                            <td class="text-sm whitespace-nowrap">{{ now()->format($country->date_format) }} · {{ $numbers[$country->number_format] ?? $country->number_format }}</td>
                            <td class="text-sm">{{ number_format($country->businesses_count) }}</td>
                            <td class="text-end">
                                @if($may('admin.countries.edit'))
                                    <a href="{{ route('admin.countries.edit', $country->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="map" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No country matches.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($countries->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $countries->links() }}</div>
        @endif
    </div>
@endsection
