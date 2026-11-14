{{-- The incident's timeline (Doc 15 section 15.23), newest first: each note, by whom and when, with the status it moved and who it made responsible. --}}
<div class="card">
    <h3 class="section-title mb-4">{{ __('Timeline') }}</h3>

    @foreach($incident->notes as $note)
        <div class="flex gap-3 pb-4 mb-4 border-b border-[var(--card-border)] last:border-b-0 last:mb-0 last:pb-0">
            <div class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center bg-[var(--bg-secondary)] text-[var(--text-muted)]">
                <i data-lucide="{{ $note->status_to === \App\Models\Admin\Incident::RESOLVED ? 'circle-check' : 'message-square' }}" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-[var(--text-muted)]">
                    <span class="font-semibold text-[var(--text-primary)]">{{ $note->author?->name ?? __('An admin') }}</span>
                    · {{ $note->created_at->format('M d, Y H:i') }} UTC
                </p>
                @if($note->status_to || $note->responsible_to)
                    <p class="text-xs mt-1">
                        @if($note->status_to)
                            <span class="badge badge-info">{{ $note->status_from ? __(\App\Models\Admin\Incident::STATUSES[$note->status_from]).' → ' : '' }}{{ __(\App\Models\Admin\Incident::STATUSES[$note->status_to]) }}</span>
                        @endif
                        @if($note->responsible_to)
                            <span class="badge badge-inactive">{{ __('Responsible: :name', ['name' => $note->responsible_to]) }}</span>
                        @endif
                    </p>
                @endif
                <p class="text-sm mt-1 whitespace-pre-line text-[var(--text-secondary)]">{{ $note->body }}</p>
            </div>
        </div>
    @endforeach
</div>
