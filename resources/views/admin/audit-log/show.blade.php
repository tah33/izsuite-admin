@extends('layouts.admin')
@section('title', __('Audit Entry').' #'.$entry->id)

{{-- One audit entry in full (Doc 15 section 15.26). It is never changed. --}}
@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.audit-log.index') }}" class="text-[var(--primary)]">{{ __('Audit Log') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>#{{ $entry->id }}</span>
        </div>
        <h1 class="page-title">{{ $entry->description }}</h1>
        <p class="page-subtitle">{{ $entry->created_at->format('M d, Y H:i:s') }}</p>
    </div>

    @php
        $facts = [
            __('Admin')      => $entry->admin_name ?? ($entry->properties['email'] ?? __('Unknown')),
            __('Role')       => $entry->role_name ?? '-',
            __('Action')     => $entry->action,
            __('Result')     => __(\App\Models\Admin\AdminAuditLog::RESULTS[$entry->result] ?? $entry->result),
            __('Record')     => $entry->record() ?? '-',
            __('Reason')     => $entry->reason ?? '-',
            __('Approval')   => $entry->approval === 'password' ? __('Password given again') : ($entry->approval ?? '-'),
            __('IP Address') => $entry->ip_address ?? '-',
            __('Browser')    => $entry->browser(),
            __('Session')    => $entry->session ?? '-',
        ];
        $other = collect($entry->properties ?? [])->except(['secrets_changed']);
    @endphp

    <div class="card p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            @foreach($facts as $label => $value)
                <div>
                    <dt class="text-[var(--text-muted)] mb-0.5">{{ $label }}</dt>
                    <dd class="font-medium text-[var(--text-primary)] break-words">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="card p-6 mb-6">
        <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">{{ __('Previous and New Values') }}</h2>

        @if(! empty($entry->changes))
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ __('Setting') }}</th><th>{{ __('Before') }}</th><th>{{ __('After') }}</th></tr></thead>
                    <tbody>
                        @foreach($entry->changes as $change)
                            <tr>
                                <td class="text-sm font-medium">{{ $change['setting'] ?? '-' }}</td>
                                <td class="text-sm text-[var(--text-muted)] break-all">{{ is_bool($change['from'] ?? null) ? ($change['from'] ? __('Yes') : __('No')) : ($change['from'] ?? '-') }}</td>
                                <td class="text-sm break-all">{{ is_bool($change['to'] ?? null) ? ($change['to'] ? __('Yes') : __('No')) : ($change['to'] ?? '-') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-[var(--text-muted)]">{{ __('No values were kept for this entry.') }}</p>
        @endif

        @if(! empty($entry->properties['secrets_changed']))
            <p class="text-sm text-[var(--text-muted)] mt-4">
                {{ __('Secrets changed, whose values are never kept:') }} <span class="font-medium text-[var(--text-primary)]">{{ implode(', ', $entry->properties['secrets_changed']) }}</span>
            </p>
        @endif
    </div>

    @if($other->isNotEmpty())
        <div class="card p-6">
            <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">{{ __('More Details') }}</h2>
            <pre class="text-xs whitespace-pre-wrap break-all text-[var(--text-secondary)]">{{ json_encode($other, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
@endsection
