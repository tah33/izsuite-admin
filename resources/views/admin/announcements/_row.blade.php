{{-- One announcement in the list (Doc 15 section 15.21): its priority, state now, audience, days and how many dismissed it. Expects $announcement, $now, $badges, $tones and $may. --}}
@php
    $state   = $announcement->stateAt($now);
    $targets = $announcement->targets ?? [];
    $named   = match ($announcement->audience) {
        'packages'   => implode(', ', array_map('ucfirst', $targets)),
        'countries'  => implode(', ', array_map(fn ($code) => \App\Support\Countries::name($code) ?? $code, $targets)),
        'apps'       => implode(', ', array_map(fn ($family) => \Illuminate\Support\Str::headline($family), $targets)),
        'businesses' => implode(', ', array_map(fn ($id) => sprintf('BW-%06d', $id), $targets)),
        default      => null,
    };
@endphp
<tr>
    <td class="min-w-[240px]">
        <span class="font-semibold text-[var(--text-primary)]">{{ $announcement->title }}</span>
        <span class="badge {{ $tones[$announcement->priority] ?? 'badge-info' }} ms-1">{{ __(\App\Models\Admin\Announcement::PRIORITIES[$announcement->priority] ?? $announcement->priority) }}</span>
        <span class="block text-xs text-[var(--text-muted)] max-w-md truncate">{{ $announcement->message }}</span>
    </td>
    <td class="whitespace-nowrap">
        <span class="badge {{ $badges[$state] }}">{{ __(\App\Models\Admin\Announcement::STATES[$state]) }}</span>
        @unless($announcement->dismissible)
            <span class="block text-xs mt-1 text-[var(--text-muted)]">{{ __('Not dismissible') }}</span>
        @endunless
    </td>
    <td class="text-xs text-[var(--text-secondary)]">
        {{ __(\App\Models\Admin\Announcement::AUDIENCES[$announcement->audience] ?? $announcement->audience) }}
        @if($named)
            <span class="block text-[var(--text-muted)]">{{ $named }}</span>
        @endif
    </td>
    <td class="text-xs whitespace-nowrap">
        {{ $announcement->starts_at ? __('From :day', ['day' => $announcement->starts_at->format('M d, Y H:i')]) : __('From when saved') }}
        <span class="block">{{ $announcement->ends_at ? __('Until :day', ['day' => $announcement->ends_at->format('M d, Y H:i')]) : __('No end') }}</span>
    </td>
    <td class="text-sm">{{ $announcement->dismissible ? trans_choice(':count person|:count people', $announcement->dismissals_count) : '—' }}</td>
    <td class="text-end whitespace-nowrap">
        @if($may('admin.announcements.edit'))
            <a href="{{ route('admin.announcements.edit', $announcement->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></a>
        @endif
        @if($may('admin.announcements.destroy'))
            <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-announcement-{{ $announcement->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
            <x-confirm-modal
                id="delete-announcement-{{ $announcement->id }}"
                :title="__('Delete This Announcement?')"
                :message="__(':title goes from everyone\'s screen at once.', ['title' => $announcement->title])"
                :action="route('admin.announcements.destroy', $announcement->id)"
                method="DELETE"
                :confirm-text="__('Delete')"
                confirm-class="btn-danger"
                icon="trash-2"
            />
        @endif
    </td>
</tr>
