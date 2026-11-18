{{-- Announcements for izSuite's admins (Doc 15 section 15.21 - Phase F1), the most urgent first; each admin dismisses one for themselves. Expects $adminAnnouncements. --}}
@php
    $tones = [
        'info'      => 'bg-[var(--info-light)] text-[#093C9A]',
        'important' => 'bg-[var(--warning-light)] text-[#92400E]',
        'emergency' => 'bg-[var(--danger-light)] text-[#991B1B]',
    ];
@endphp
@foreach($adminAnnouncements ?? [] as $announcement)
    <div class="flex items-start gap-3 p-3 rounded-xl mb-4 text-sm {{ $tones[$announcement->priority] ?? $tones['info'] }}" role="status">
        <i data-lucide="{{ $announcement->priority === 'emergency' ? 'siren' : 'megaphone' }}" class="w-4 h-4 mt-0.5 shrink-0"></i>
        <div class="flex-1 min-w-0">
            <p class="font-semibold">{{ $announcement->title }}</p>
            <p>{{ $announcement->message }}</p>
            @if($announcement->link_url)
                <a href="{{ $announcement->link_url }}" class="font-medium underline" @if(str_starts_with($announcement->link_url, 'http')) target="_blank" rel="noopener" @endif>{{ $announcement->link_label }}</a>
            @endif
        </div>
        @if($announcement->dismissible)
            <form action="{{ route('admin.announcements.dismiss', $announcement->id) }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="opacity-70 hover:opacity-100" title="{{ __('Dismiss') }}" aria-label="{{ __('Dismiss') }}"><i data-lucide="x" class="w-4 h-4"></i></button>
            </form>
        @endif
    </div>
@endforeach
