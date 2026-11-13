{{-- The business's support cases (Doc 15 section 15.22): the latest ten, each opening in Admin > Tickets. Expects $supportCases and $may. --}}
<div class="card p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Support Cases') }}</h2>
            <p class="text-sm text-[var(--text-muted)]">{{ __('What the business has asked izSuite, newest activity first.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($supportCases->isNotEmpty() && $may('admin.tickets.index'))
                <a href="{{ route('admin.tickets.index', ['workspace' => $business->id]) }}" class="btn btn-secondary btn-sm">{{ __('All Cases') }}</a>
            @endif
            @if($may('admin.tickets.create'))
                <a href="{{ route('admin.tickets.create', ['workspace' => $business->id]) }}" class="btn btn-secondary btn-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i>{{ __('New Case') }}
                </a>
            @endif
        </div>
    </div>

    @if($supportCases->isEmpty())
        <p class="text-sm text-[var(--text-muted)]">{{ __('None yet.') }}</p>
    @else
        <ul class="divide-y divide-[var(--card-border)]">
            @foreach($supportCases as $case)
                <li class="py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="min-w-0">
                        @if($may('admin.tickets.show'))
                            <a href="{{ route('admin.tickets.show', $case->id) }}" class="font-medium text-[var(--primary)]">#{{ $case->id }} {{ $case->subject }}</a>
                        @else
                            <span class="font-medium text-[var(--text-primary)]">#{{ $case->id }} {{ $case->subject }}</span>
                        @endif
                        <span class="text-[var(--text-muted)]">· {{ $case->user?->name ?? __('Someone who left') }}</span>
                    </span>
                    <span class="flex items-center gap-3 text-xs text-[var(--text-muted)] whitespace-nowrap">
                        <span class="badge {{ in_array($case->status, ['resolved', 'closed'], true) ? 'badge-inactive' : 'badge-warning' }}">{{ __($case->statusLabel()) }}</span>
                        {{ $case->updated_at?->diffForHumans() }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
