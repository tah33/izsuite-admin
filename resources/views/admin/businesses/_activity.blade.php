{{-- What admins did to this business, from the audit log (Doc 15 sections 15.5 and 15.26). --}}
<div class="card p-6">
    <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Admin Activity') }}</h2>
    <p class="text-sm text-[var(--text-muted)] mb-4">{{ __('The latest admin actions on this business, from the audit log.') }}</p>

    @if($activity->isEmpty())
        <p class="text-sm text-[var(--text-muted)]">{{ __('None yet.') }}</p>
    @else
        <ul class="divide-y divide-[var(--card-border)]">
            @foreach($activity as $entry)
                <li class="py-2.5 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                    <span>
                        <span class="text-[var(--text-primary)]">{{ $entry->description }}</span>
                        <span class="text-[var(--text-muted)]">· {{ $entry->admin_name ?? __('Unknown') }}</span>
                    </span>
                    <span class="text-xs text-[var(--text-muted)] whitespace-nowrap">
                        {{ $entry->created_at->format('M d, Y H:i') }}
                        @if(auth()->user()->role?->hasPermission('admin.audit-log.show'))
                            · <a href="{{ route('admin.audit-log.show', $entry->id) }}" class="text-[var(--primary)]">{{ __('Details') }}</a>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
