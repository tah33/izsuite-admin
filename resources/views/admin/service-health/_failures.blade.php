{{-- The latest failures behind e-mail, imports and card payments (Doc 15 section 15.23). Failed e-mails run again from Failed Jobs. --}}
@php
    $lists = [
        [__('Notification E-mails That Failed'), $notificationEmails, fn ($row) => [$row->notification?->user?->email ?? '-', $row->notification?->title ?? '', $row->failure]],
        [__('Document E-mails That Failed'), $documentEmails, fn ($row) => [$row->to, ($row->workspace?->name ?? '-').' · '.$row->subject, $row->failure]],
        [__('Imports That Failed'), $imports, fn ($row) => [$row->workspace?->name ?? '-', ucfirst((string) $row->kind).' · '.$row->file_name, $row->error]],
        [__('Card Payment Failures'), $providerFailures, fn ($row) => [ucfirst($row->kind), ucfirst($row->service), $row->message]],
    ];
@endphp
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
    @foreach($lists as [$title, $rows, $cells])
        <div class="card overflow-hidden">
            <h3 class="section-title mb-3">{{ $title }}</h3>
            @forelse($rows as $row)
                @php
                    [$who, $what, $why] = $cells($row);
                @endphp
                <div class="py-2 border-t border-[var(--card-border)] first:border-t-0 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="font-medium text-[var(--text-primary)] truncate">{{ $who }}</span>
                        <span class="text-xs text-[var(--text-muted)] whitespace-nowrap">{{ ($row->updated_at ?? $row->created_at)?->format('M d, H:i') }}</span>
                    </div>
                    <p class="text-xs text-[var(--text-secondary)] truncate">{{ $what }}</p>
                    @if($why)
                        <p class="text-xs text-[var(--danger)]">{{ \Illuminate\Support\Str::limit($why, 200) }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-[var(--text-muted)]">{{ __('None.') }}</p>
            @endforelse
        </div>
    @endforeach
</div>
