{{-- A contact message's sidebar: its details and its audit trail --}}
{{-- Technical Metadata --}}
<div class="card border-[var(--card-border)] shadow-sm p-0 overflow-hidden">
    <div class="px-6 py-4 bg-[var(--bg-secondary)] border-b border-[var(--card-border)]">
        <h3 class="font-semibold text-[var(--text-primary)] uppercase tracking-wider text-xs">{{ __('Ticket Information') }}</h3>
    </div>
    <div class="p-6 space-y-4">
        <div class="flex justify-between items-center text-sm border-b border-[var(--card-border)] pb-2">
            <span class="text-[var(--text-muted)]">{{ __('Status') }}</span>
            <span class="font-medium @if($contactMessage->status === 'new') text-[var(--warning)] @else text-[var(--primary)] @endif">{{ ucfirst($contactMessage->status) }}</span>
        </div>
        <div class="flex justify-between items-center text-sm border-b border-[var(--card-border)] pb-2">
            <span class="text-[var(--text-muted)]">{{ __('Sender') }}</span>
            <span class="font-medium">{{ $contactMessage->name }}</span>
        </div>
        <div class="flex justify-between items-center text-sm border-b border-[var(--card-border)] pb-2">
            <span class="text-[var(--text-muted)]">{{ __('Email') }}</span>
            <a href="mailto:{{ $contactMessage->email }}" class="font-medium text-[var(--primary)] hover:underline">{{ $contactMessage->email }}</a>
        </div>
        <div class="flex justify-between items-center text-sm border-b border-[var(--card-border)] pb-2">
            <span class="text-[var(--text-muted)]">{{ __('Subject') }}</span>
            <span class="font-medium line-clamp-1 truncate ml-4" title="{{ $contactMessage->subject }}">{{ $contactMessage->subject }}</span>
        </div>
        <div class="flex justify-between items-center text-sm">
            <span class="text-[var(--text-muted)]">{{ __('Submitted') }}</span>
            <span class="font-medium">{{ $contactMessage->created_at?->format('M d, Y H:i') }}</span>
        </div>
    </div>
</div>

{{-- Audit Trail --}}
<div class="card border-[var(--card-border)] shadow-sm p-0 overflow-hidden text-[10px]">
    <div class="px-6 py-4 bg-[var(--bg-secondary)] border-b border-[var(--card-border)]">
        <h3 class="font-semibold text-[var(--text-primary)] uppercase tracking-wider text-[10px]">{{ __('Audit History') }}</h3>
    </div>
    <div class="p-6">
        <ul class="space-y-4 relative before:absolute before:inset-y-0 before:left-2 before:w-px before:bg-[var(--card-border)]">
            <li class="relative pl-6">
                <span class="absolute left-0 top-1 w-4 h-4 rounded-full bg-[var(--primary-light)] border-2 border-white"></span>
                <div class="text-[var(--text-primary)] font-semibold">{{ __('Ticket Received') }}</div>
                <div class="text-[var(--text-muted)] mt-0.5">{{ $contactMessage->created_at?->diffForHumans() }}</div>
            </li>
            @if($contactMessage->status === 'replied')
                <li class="relative pl-6">
                    <span class="absolute left-0 top-1 w-4 h-4 rounded-full bg-[var(--success)] border-2 border-white"></span>
                    <div class="text-[var(--text-primary)] font-semibold">{{ __('Admin Replied') }}</div>
                    <div class="text-[var(--text-muted)] mt-0.5">{{ $contactMessage->replied_at?->diffForHumans() }}</div>
                    <div class="text-[var(--text-muted)] text-[8px] italic mt-1">{{ __('Action by') }} {{ $contactMessage->replier?->name ?? 'Admin' }}</div>
                </li>
            @endif
        </ul>
    </div>
</div>
