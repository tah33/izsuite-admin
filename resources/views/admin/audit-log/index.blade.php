@extends('layouts.admin')
@section('title', __('Audit Log'))

{{-- Doc 15 section 15.26: what admins did and tried. Read-only: nothing here changes an entry. --}}
@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Audit Log') }}</h1>
        <p class="page-subtitle">{{ __('Every admin action and sign-in, with who, when, from where, and how it ended. Entries are never changed or deleted.') }}</p>
    </div>

    @include('admin.audit-log._filters')

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Admin') }}</th>
                        <th>{{ __('What') }}</th>
                        <th>{{ __('Record') }}</th>
                        <th>{{ __('Result') }}</th>
                        <th>{{ __('IP Address') }}</th>
                        <th class="text-end">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="text-sm whitespace-nowrap">{{ $entry->created_at->format('M d, Y H:i:s') }}</td>
                            <td>
                                <span class="font-medium text-[var(--text-primary)]">{{ $entry->admin_name ?? ($entry->properties['email'] ?? __('Unknown')) }}</span>
                                @if($entry->role_name)
                                    <span class="block text-xs text-[var(--text-muted)]">{{ $entry->role_name }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-sm text-[var(--text-primary)]">{{ $entry->description }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">
                                    {{ $entry->action }}@if($entry->reason) · {{ __('Reason given') }}@endif
                                </span>
                            </td>
                            <td class="text-sm text-[var(--text-muted)] whitespace-nowrap">{{ $entry->record() ?? '-' }}</td>
                            <td>
                                <span class="badge {{ ['succeeded' => 'badge-success', 'failed' => 'badge-danger', 'refused' => 'badge-warning'][$entry->result] ?? 'badge-inactive' }}">
                                    {{ __(\App\Models\Admin\AdminAuditLog::RESULTS[$entry->result] ?? $entry->result) }}
                                </span>
                            </td>
                            <td class="text-sm text-[var(--text-muted)] font-mono">{{ $entry->ip_address ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.audit-log.show', $entry->id) }}" class="btn btn-secondary btn-sm">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="scroll-text" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No entries match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
@endsection
