@extends('layouts.admin')
@section('title', __('Support Sessions'))

{{-- Doc 15 section 15.22: every support session, for review - who, where, for which case and why, with what access and
     the customer's agreement, when, and the sensitive areas it opened. --}}
@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Support Sessions') }}</h1>
        <p class="page-subtitle">{{ __('izSuite support in businesses: who, where, for which case and why, and what it opened.') }}</p>
    </div>

    <form action="{{ route('admin.support-sessions.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business or admin...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-36 shrink-0">
            <select name="state" class="form-select" aria-label="{{ __('State') }}">
                <option value="">{{ __('Any state') }}</option>
                <option value="live" @selected(request('state') === 'live')>{{ __('Open') }}</option>
                <option value="ended" @selected(request('state') === 'ended')>{{ __('Ended') }}</option>
            </select>
        </div>
        <div class="w-40 shrink-0">
            <select name="access" class="form-select" aria-label="{{ __('Access') }}">
                <option value="">{{ __('Any access') }}</option>
                <option value="read_only" @selected(request('access') === 'read_only')>{{ __('Read only') }}</option>
                <option value="edit" @selected(request('access') === 'edit')>{{ __('Edit access') }}</option>
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.support-sessions.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Admin') }}</th>
                        <th>{{ __('Case and Reason') }}</th>
                        <th>{{ __('Access') }}</th>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Opened') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td>
                                @if($session->workspace && auth()->user()->role?->hasPermission('admin.businesses.show'))
                                    <a href="{{ route('admin.businesses.show', $session->workspace_id) }}" class="font-semibold text-[var(--primary)]">{{ $session->workspace->name }}</a>
                                @else
                                    <span class="font-semibold">{{ $session->workspace?->name ?? __('A deleted business') }}</span>
                                @endif
                            </td>
                            <td class="text-sm whitespace-nowrap">{{ $session->admin_name }}</td>
                            <td class="text-sm">
                                <span class="block">#{{ $session->ticket_id }} {{ $session->ticket?->subject }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $session->reason }}</span>
                            </td>
                            <td class="text-sm">
                                <span class="badge {{ $session->canEdit() ? 'badge-warning' : 'badge-inactive' }}">{{ __($session->accessLabel()) }}</span>
                                @if($session->customer_authorization)
                                    <span class="block text-xs text-[var(--text-muted)] mt-1">{{ __('Agreed: :how', ['how' => $session->customer_authorization]) }}</span>
                                @endif
                            </td>
                            <td class="text-xs text-[var(--text-muted)] whitespace-nowrap">
                                <span class="block">{{ $session->started_at->format('M d, H:i') }}</span>
                                {{ $session->isLive()
                                    ? __('Open until :time', ['time' => $session->expires_at->format('H:i')])
                                    : __('Ended :time', ['time' => ($session->ended_at ?? $session->expires_at)->format('M d, H:i')]) }}
                            </td>
                            <td class="text-xs text-[var(--text-muted)]">{{ $session->areas ? implode(', ', $session->areas) : __('No sensitive area') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="life-buoy" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No support sessions match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sessions->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $sessions->links() }}</div>
        @endif
    </div>
@endsection
